<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';
require_once __DIR__ . '/BookingFileController.php';

/**
 * Master Booking controller.
 * Booking-level Hotel/Transport lines live in normalized hotel_stays and
 * transport_transfers tables. All monetary/assignment changes are transactional.
 */
class BookingController {
    private static function actor(): string { return Session::getActor(); }

    public static function create(array $data): array {
        $validation = self::validateCore($data);
        if (!$validation['success']) return $validation;

        Database::beginTransaction();
        try {
            $id = self::insertBooking($data, null);
            self::syncServices($id, $data);
            self::audit($id, 'created', null, self::auditSnapshot($id));
            Database::commit();
            return ['success'=>true,'message'=>'Booking record created successfully.','booking_id'=>$id,'booking_code'=>self::getBookingCode($id)];
        } catch (Throwable $e) {
            Database::rollBack();
            return ['success'=>false,'message'=>'Error creating booking: '.$e->getMessage()];
        }
    }

    public static function update(int $id, array $data): array {
        if ($id <= 0) return ['success'=>false,'message'=>'Invalid booking ID.'];
        $validation = self::validateCore($data, true);
        if (!$validation['success']) return $validation;
        $existing = Database::fetchOne("SELECT * FROM master_bookings WHERE id=? AND deleted_at IS NULL",[$id]);
        if (!$existing) return ['success'=>false,'message'=>'Booking not found.'];

        Database::beginTransaction();
        try {
            $before = self::auditSnapshot($id);
            self::insertBooking($data, $id);
            self::syncServices($id, $data);
            self::audit($id, 'updated', $before, self::auditSnapshot($id));
            Database::commit();
            return ['success'=>true,'message'=>'Booking details updated successfully.'];
        } catch (Throwable $e) {
            Database::rollBack();
            return ['success'=>false,'message'=>'Error updating booking: '.$e->getMessage()];
        }
    }

    public static function bulkUpdate(array $data): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($data['booking_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if (!$ids) return ['success'=>false,'message'=>'Select at least one booking.'];

        $updates=[]; $params=[];
        $fields=[
            'agent_id'=>static fn(mixed $value): int=>(int)$value,
            'vendor_id'=>static fn(mixed $value): int=>(int)$value,
            'flight_number'=>static fn(mixed $value): string=>strtoupper(trim((string)$value)),
            'arrival_date'=>fn(mixed $value): ?string=>self::dateOrNull($value),
            'departure_date'=>fn(mixed $value): ?string=>self::dateOrNull($value),
            'buy_rate_pkr'=>fn(mixed $value): float=>self::money($value),
            'sell_rate_pkr'=>fn(mixed $value): float=>self::money($value),
            'ticket_buy_rate_pkr'=>fn(mixed $value): float=>self::money($value),
            'ticket_sell_rate_pkr'=>fn(mixed $value): float=>self::money($value)
        ];
        foreach($fields as $field=>$normalizer){
            if(!array_key_exists($field,$data)||trim((string)$data[$field])==='')continue;
            $value=$normalizer($data[$field]);
            if(in_array($field,['arrival_date','departure_date'],true)&&$value===null)return ['success'=>false,'message'=>'Please provide valid arrival and departure dates.'];
            if(in_array($field,['agent_id','vendor_id'],true)&&$value<=0)return ['success'=>false,'message'=>'Please select a valid Agent and Vendor.'];
            $updates[]=$field.'=?';$params[]=$value;
        }
        $transport=(array)($data['transport']??[]);
        $withTransport=!empty($transport['enabled']);
        if(!$updates&&!$withTransport)return ['success'=>false,'message'=>'Enter at least one field to update.'];

        Database::beginTransaction();
        try{
            if($updates){
                $placeholders=implode(',',array_fill(0,count($ids),'?'));
                $params=array_merge($params,[self::actor()],$ids);
                Database::execute('UPDATE master_bookings SET '.implode(',',$updates).',updated_by=?,updated_at=NOW() WHERE deleted_at IS NULL AND id IN ('.$placeholders.')',$params);
            }
            $legs=$withTransport?self::assignGroupTransport($ids,$transport,$data):0;
            Database::commit();
        }catch(Throwable $e){
            Database::rollBack();
            return ['success'=>false,'message'=>$e->getMessage()];
        }
        $message=count($ids).' booking(s) updated successfully.';
        if($withTransport)$message.=' '.$legs.' group transfer(s) saved under the family head.';
        return ['success'=>true,'message'=>$message,'updated'=>count($ids)];
    }

    /**
     * Bulk "Transport" for a family/group: ONE transport_bookings row per route (not per member),
     * booked under the chosen family head with pax_count = group size. The agent ledger therefore
     * gets a single debit per route/vehicle instead of one per traveller.
     * Re-applying updates the group's existing row for the same route instead of duplicating it.
     */
    private static function assignGroupTransport(array $ids,array $transport,array $data):int{
        $headId=(int)($transport['head_id']??0);
        if(!in_array($headId,$ids,true))throw new RuntimeException('Select the Family Head for the transport.');
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $members=Database::fetchAll("SELECT id,agent_id,vendor_id,passenger_name,passport_number,flight_number,arrival_date,departure_date FROM master_bookings WHERE deleted_at IS NULL AND id IN ($placeholders)",$ids);
        $head=null;
        foreach($members as $m){if((int)$m['id']===$headId)$head=$m;}
        if(!$head)throw new RuntimeException('Family Head booking not found.');

        $agentId=(int)($data['agent_id']??0);
        if($agentId<=0){
            $agentIds=array_values(array_unique(array_filter(array_map(static fn($m)=>(int)$m['agent_id'],$members))));
            if(count($agentIds)!==1)throw new RuntimeException('The selected travellers belong to different (or no) agents. Choose the Agent above so the transport goes to one ledger.');
            $agentId=$agentIds[0];
        }
        $vendorId=(int)($data['vendor_id']??0)?:((int)$head['vendor_id']?:null);

        $defaultType=trim((string)($transport['type']??''))?:'CAR';
        $defaultBuy=self::money($transport['buy']??0);
        $defaultSell=self::money($transport['sell']??0);
        $routes=[];
        foreach((array)($transport['routes']??[]) as $r){
            if(!is_array($r))continue;
            $route=strtoupper(preg_replace('/\s+/','',(string)($r['route']??'')));
            if($route===''||isset($routes[$route]))continue;
            $routes[$route]=$r;
        }
        if(!$routes)throw new RuntimeException('Tick at least one transport route.');

        $actor=self::actor();
        $paxCount=count($members);
        $saved=0;
        foreach($routes as $route=>$r){
            $type=trim((string)($r['type']??''))?:$defaultType;
            $buy=trim((string)($r['buy']??''))!==''?self::money($r['buy']):$defaultBuy;
            $sell=trim((string)($r['sell']??''))!==''?self::money($r['sell']):$defaultSell;
            $date=self::dateOrNull($r['date']??null);
            if(!$date)throw new RuntimeException("Enter a date for {$route}.");
            $flight=null;

            $existingId=(int)Database::fetchValue(
                "SELECT id FROM transport_bookings WHERE deleted_at IS NULL AND auto_generated=0 AND master_booking_id IN ($placeholders) AND UPPER(REPLACE(route_details,' ',''))=? ORDER BY id LIMIT 1",
                array_merge($ids,[$route])
            );
            $values=[$headId,$agentId,$vendorId,$date,$flight,$head['passenger_name'],$head['passport_number']?:null,$paxCount,$type,$route,$buy,$sell];
            if($existingId>0){
                Database::execute("UPDATE transport_bookings SET master_booking_id=?,agent_id=?,vendor_id=?,service_date=?,flight_number=?,pax_name=?,passport_number=?,pax_count=?,vehicle_type=?,route_details=?,buy_rate_pkr=?,sell_rate_pkr=?,updated_by=?,updated_at=NOW() WHERE id=?",array_merge($values,[$actor,$existingId]));
            }else{
                Database::execute("INSERT INTO transport_bookings (master_booking_id,agent_id,vendor_id,service_date,flight_number,terminal,pax_name,passport_number,pax_count,vehicle_type,pickup_time,route_details,buy_rate_pkr,sell_rate_pkr,status,auto_generated,created_by,created_at,updated_by,updated_at) VALUES (?,?,?,?,?,'Terminal 1',?,?,?,?,'09:00:00',?,?,?,'scheduled',0,?,NOW(),?,NOW())",array_merge(array_slice($values,0,9),[$route,$buy,$sell,$actor,$actor]));
            }
            $saved++;
        }
        return $saved;
    }

    private static function validateCore(array $data, bool $isUpdate=false): array {
        if (!$isUpdate && isset($data['id']) && (int)$data['id'] > 0) return ['success'=>false,'message'=>'New booking cannot contain an existing ID.'];
        $agentId=!empty($data['agent_id']) ? (int)$data['agent_id'] : null; $vendorId=!empty($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        $name=trim((string)($data['passenger_name']??'')); $passport=trim((string)($data['passport_number']??''));
        if ($name==='') return ['success'=>false,'message'=>'Passenger Name is required.'];
        if ($passport==='') return ['success'=>false,'message'=>'Passport Number is required.'];
        return ['success'=>true];
    }

    private static function insertBooking(array $data, ?int $id): int {
        $bookingDate=self::dateOrNull($data['booking_date']??null) ?: date('Y-m-d');
        $agentId=!empty($data['agent_id']) ? (int)$data['agent_id'] : null; $vendorId=!empty($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        $name=trim((string)($data['passenger_name']??'')); $passport=strtoupper(trim((string)($data['passport_number']??'')));
        $flight=strtoupper(trim((string)($data['flight_number']??'')));
        $existing = $id === null ? [] : (Database::fetchOne("SELECT flight_itinerary_json,gender,pax_type FROM master_bookings WHERE id=?",[$id]) ?: []);
        $itinerary = array_key_exists('flight_itinerary', $data)
            ? self::normalizeItinerary((array)$data['flight_itinerary'])
            : (string)($existing['flight_itinerary_json'] ?? '[]');
        $itineraryJson = is_string($itinerary) ? $itinerary : json_encode($itinerary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($itineraryJson === false) throw new RuntimeException('Unable to encode the flight itinerary.');
        $gender = strtoupper(trim((string)($data['gender'] ?? $existing['gender'] ?? '')));
        $gender = in_array($gender, ['M', 'F'], true) ? $gender : null;
        $paxType = trim((string)($data['pax_type'] ?? $existing['pax_type'] ?? 'Adult'));
        if (!in_array($paxType, ['Adult', 'Child', 'Infant'], true)) $paxType = 'Adult';
        $arrival=self::dateOrNull($data['arrival_date']??null); $departure=self::dateOrNull($data['departure_date']??null);
        $stay=trim((string)($data['stay_days']??'')) ?: null;
        $visaBuy=self::money($data['buy_rate_pkr']??0); $visaSell=self::money($data['sell_rate_pkr']??0);
        $ticketBuy=self::money($data['ticket_buy_rate_pkr']??$data['ticket_buy_pkr']??0); $ticketSell=self::money($data['ticket_sell_rate_pkr']??$data['ticket_sell_pkr']??0);
        $remarks=trim((string)($data['remarks']??$data['notes']??'')) ?: null;
        $status=self::status($data['status']??'draft');
        $attachHotel=!empty($data['include_hotel']) || strtoupper((string)($data['attach_hotel']??''))==='Y' ? 1 : 0;
        $attachTransport=!empty($data['include_transport']) || strtoupper((string)($data['attach_transport']??''))==='Y' ? 1 : 0;
        $cf1=trim((string)($data['custom_field1']??'')) ?: null; $cf2=trim((string)($data['custom_field2']??'')) ?: null;
        $customJson=json_encode((array)($data['custom_fields']??[]),JSON_UNESCAPED_UNICODE);
        $actor=self::actor();

        if ($id===null) {
            $code=self::generateBookingCode();
            Database::execute("INSERT INTO master_bookings (booking_code,booking_date,agent_id,vendor_id,passenger_name,passport_number,flight_number,flight_itinerary_json,gender,pax_type,arrival_date,departure_date,stay_days,buy_rate_pkr,sell_rate_pkr,ticket_buy_rate_pkr,ticket_sell_rate_pkr,attach_hotel,attach_transport,remarks,status,custom_field1,custom_field2,custom_fields_json,created_by,created_at,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW())",[$code,$bookingDate,$agentId,$vendorId,$name,$passport,$flight,$itineraryJson,$gender,$paxType,$arrival,$departure,$stay,$visaBuy,$visaSell,$ticketBuy,$ticketSell,$attachHotel,$attachTransport,$remarks,$status,$cf1,$cf2,$customJson,$actor,$actor]);
            return Database::lastInsertId();
        }
        Database::execute("UPDATE master_bookings SET booking_date=?,agent_id=?,vendor_id=?,passenger_name=?,passport_number=?,flight_number=?,flight_itinerary_json=?,gender=?,pax_type=?,arrival_date=?,departure_date=?,stay_days=?,buy_rate_pkr=?,sell_rate_pkr=?,ticket_buy_rate_pkr=?,ticket_sell_rate_pkr=?,attach_hotel=?,attach_transport=?,remarks=?,status=?,custom_field1=?,custom_field2=?,custom_fields_json=?,updated_by=?,updated_at=NOW() WHERE id=?",[$bookingDate,$agentId,$vendorId,$name,$passport,$flight,$itineraryJson,$gender,$paxType,$arrival,$departure,$stay,$visaBuy,$visaSell,$ticketBuy,$ticketSell,$attachHotel,$attachTransport,$remarks,$status,$cf1,$cf2,$customJson,$actor,$id]);
        return $id;
    }

    private static function normalizeItinerary(array $segments): array {
        if (count($segments) > 40) throw new RuntimeException('A flight itinerary cannot contain more than 40 segments.');
        $normalized = [];
        foreach ($segments as $index => $segment) {
            if (!is_array($segment)) continue;
            $from = strtoupper(substr(preg_replace('/[^A-Z]/i', '', trim((string)($segment['from'] ?? ''))), 0, 3));
            $to = strtoupper(substr(preg_replace('/[^A-Z]/i', '', trim((string)($segment['to'] ?? ''))), 0, 3));
            $flight = strtoupper(substr(preg_replace('/[^A-Z0-9-]/i', '', trim((string)($segment['flight'] ?? ''))), 0, 20));
            $depDate = self::dateOrNull($segment['dep_date'] ?? null);
            $arrDate = self::dateOrNull($segment['arr_date'] ?? null);
            $depTime = self::timeOrNull($segment['dep_time'] ?? null);
            $arrTime = self::timeOrNull($segment['arr_time'] ?? null);
            if ($from === '' && $to === '' && $flight === '' && !$depDate && !$arrDate) continue;
            foreach (['dep_date' => $depDate, 'arr_date' => $arrDate] as $label => $date) {
                if (trim((string)($segment[$label] ?? '')) !== '' && $date === null) {
                    throw new RuntimeException('Flight segment #' . ((int)$index + 1) . ' has an invalid date.');
                }
            }
            if (($from === '') !== ($to === '')) throw new RuntimeException('Flight segment #' . ((int)$index + 1) . ' must include both route airports or neither.');
            foreach (['dep_time' => $depTime, 'arr_time' => $arrTime] as $label => $time) {
                if (trim((string)($segment[$label] ?? '')) !== '' && $time === null) {
                    throw new RuntimeException('Flight segment #' . ((int)$index + 1) . ' has an invalid time.');
                }
            }
            $normalized[] = [
                'flight' => $flight,
                'from' => $from,
                'to' => $to,
                'from_city' => trim((string)($segment['from_city'] ?? '')),
                'to_city' => trim((string)($segment['to_city'] ?? '')),
                'dep_date' => $depDate,
                'dep_time' => $depTime ? substr($depTime, 0, 5) : '',
                'arr_date' => $arrDate,
                'arr_time' => $arrTime ? substr($arrTime, 0, 5) : '',
                'from_terminal' => trim((string)($segment['from_terminal'] ?? '')),
                'to_terminal' => trim((string)($segment['to_terminal'] ?? '')),
                'baggage' => trim((string)($segment['baggage'] ?? '')),
                'duration' => trim((string)($segment['duration'] ?? '')),
            ];
        }
        return $normalized;
    }

    private static function syncServices(int $bookingId, array $data): void {
        $actor=self::actor();
        Database::execute("UPDATE hotel_stays SET deleted_at=NOW(),updated_by=?,updated_at=NOW() WHERE booking_id=? AND deleted_at IS NULL",[$actor,$bookingId]);
        Database::execute("UPDATE transport_transfers SET deleted_at=NOW(),updated_by=?,updated_at=NOW() WHERE booking_id=? AND deleted_at IS NULL",[$actor,$bookingId]);

        if (!empty($data['include_hotel']) || strtoupper((string)($data['attach_hotel']??''))==='Y') {
            $stays=self::normalizeStays((array)($data['hotel_stays']??$data['stays']??[]));
            if (!$stays) throw new RuntimeException('At least one complete hotel stay is required when Hotel Accommodation is attached.');
            foreach ($stays as $i=>$s) {
                Database::execute("INSERT INTO hotel_stays (booking_id,line_item_id,sequence,city,hotel_name,room_type,checkin_date,checkout_date,nights,per_night_buy,per_night_sell,currency,meal_plan,view,pax,net_accommodation_charge,vat,notes,created_by,created_at,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW())",[$bookingId,$s['line_item_id'],$i+1,$s['city'],$s['hotel_name'],$s['room_type'],$s['checkin_date'],$s['checkout_date'],$s['nights'],$s['buy_rate'],$s['sell_rate'],'PKR',$s['meal_plan'],$s['view'],$s['pax'],$s['net_accommodation_charge'],$s['vat'],$s['notes'],$actor,$actor]);
            }
        }
        if (!empty($data['include_transport']) || strtoupper((string)($data['attach_transport']??''))==='Y') {
            $transfers=self::normalizeTransfers((array)($data['transport_transfers']??$data['transports']??[]),$data);
            foreach ($transfers as $i=>$t) {
                Database::execute("INSERT INTO transport_transfers (booking_id,line_item_id,sequence,service_date,pickup_time,flight_number,terminal,pax_name,passport_number,pax_count,vehicle_type,route_details,buy_rate,sell_rate,currency,notes,created_by,created_at,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW())",[$bookingId,$t['line_item_id'],$i+1,$t['service_date'],$t['pickup_time'],$t['flight_number'],$t['terminal'],$t['pax_name'],$t['passport_number'],$t['pax_count'],$t['vehicle_type'],$t['route_details'],$t['buy_rate'],$t['sell_rate'],'PKR',$t['notes'],$actor,$actor]);
            }
        }
    }

    private static function normalizeStays(array $stays): array {
        $out=[];
        foreach($stays as $i=>$s){ if(!is_array($s))continue; $hotel=trim((string)($s['hotel_name']??'')); if($hotel==='')continue;
            $in=self::dateOrNull($s['checkin_date']??null);$outDate=self::dateOrNull($s['checkout_date']??null); if(!$in||!$outDate)throw new RuntimeException('Hotel stay #'.($i+1).' requires valid check-in and check-out dates.');
            $d1=new DateTimeImmutable($in);$d2=new DateTimeImmutable($outDate);$n=(int)$d1->diff($d2)->days;if($d2<=$d1||$n<1)throw new RuntimeException('Hotel stay #'.($i+1).' check-out must be after check-in.');
            $room=trim((string)($s['room_type']??'Double Bed'));if($room==='__custom__')$room=trim((string)($s['custom_room_type']??''));if($room==='')$room='Double Bed';
            $out[]=['line_item_id'=>trim((string)($s['line_item_id']??''))?:bin2hex(random_bytes(8)),'city'=>in_array(($s['city']??'Makkah'),['Makkah','Madinah'],true)?$s['city']:'Makkah','hotel_name'=>$hotel,'room_type'=>$room,'checkin_date'=>$in,'checkout_date'=>$outDate,'nights'=>$n,'buy_rate'=>self::money($s['buy_rate_per_night']??$s['buy_rate']??0),'sell_rate'=>self::money($s['sell_rate_per_night']??$s['sell_rate']??0),'meal_plan'=>trim((string)($s['meal_plan']??'RO'))?:'RO','view'=>trim((string)($s['view']??''))?:null,'pax'=>max(1,(int)($s['pax']??1)),'net_accommodation_charge'=>self::money($s['net_accommodation_charge']??0),'vat'=>self::money($s['vat']??0),'notes'=>trim((string)($s['notes']??''))?:null];
        } return $out;
    }

    private static function normalizeTransfers(array $transfers,array $bookingData): array {
        if(!$transfers){$transfers=[['service_date'=>$bookingData['arrival_date']??$bookingData['booking_date']??date('Y-m-d'),'pickup_time'=>$bookingData['transport_time']??'08:00','flight_number'=>$bookingData['flight_number']??'','terminal'=>$bookingData['transport_terminal']??'Terminal 1','pax_name'=>$bookingData['passenger_name']??'','passport_number'=>$bookingData['passport_number']??'','pax_count'=>$bookingData['transport_pax_count']??1,'vehicle_type'=>$bookingData['transport_vehicle']??'Car','route_details'=>$bookingData['transport_route']??'JED-MAK','buy_rate'=>$bookingData['transport_buy_pkr']??0,'sell_rate'=>$bookingData['transport_sell_pkr']??0,'notes'=>$bookingData['transport_notes']??'']];}
        $out=[];foreach($transfers as $i=>$t){if(!is_array($t))continue;$pax=trim((string)($t['pax_name']??$bookingData['passenger_name']??''));$vehicle=trim((string)($t['vehicle_type']??'Car'));if($pax===''||$vehicle==='')throw new RuntimeException('Transport transfer #'.($i+1).' requires passenger and vehicle type.');$out[]=['line_item_id'=>trim((string)($t['line_item_id']??''))?:bin2hex(random_bytes(8)),'service_date'=>self::dateOrNull($t['service_date']??null)?:date('Y-m-d'),'pickup_time'=>self::timeOrNull($t['pickup_time']??null)?:'08:00:00','flight_number'=>strtoupper(trim((string)($t['flight_number']??$bookingData['flight_number']??''))),'terminal'=>trim((string)($t['terminal']??'Terminal 1'))?:'Terminal 1','pax_name'=>$pax,'passport_number'=>strtoupper(trim((string)($t['passport_number']??$bookingData['passport_number']??''))),'pax_count'=>max(1,(int)($t['pax_count']??1)),'vehicle_type'=>$vehicle,'route_details'=>trim((string)($t['route_details']??'JED-MAK'))?:'JED-MAK','buy_rate'=>self::money($t['buy_rate']??$t['buy_rate_pkr']??0),'sell_rate'=>self::money($t['sell_rate']??$t['sell_rate_pkr']??0),'notes'=>trim((string)($t['notes']??''))?:null];}return $out;
    }

    public static function getById(int $id): ?array {
        $booking=Database::fetchOne("SELECT mb.*,a.name AS agent_name,a.phone AS agent_phone,v.name AS vendor_name FROM master_bookings mb LEFT JOIN agents a ON a.id=mb.agent_id LEFT JOIN vendors v ON v.id=mb.vendor_id WHERE mb.id=? AND mb.deleted_at IS NULL",[$id]);if(!$booking)return null;
        $booking['hotel_stays']=Database::fetchAll("SELECT * FROM hotel_stays WHERE booking_id=? AND deleted_at IS NULL ORDER BY sequence,id",[$id]);
        $booking['transport_transfers']=Database::fetchAll("SELECT * FROM transport_transfers WHERE booking_id=? AND deleted_at IS NULL ORDER BY sequence,id",[$id]);
        $booking['attachments']=BookingFileController::getCurrentWithUrls($id);
        return $booking;
    }

    /** Lightweight autocomplete search (no attachments/services hydration) for linking a Master Booking elsewhere, e.g. Only Hotel Booking. */
    public static function searchLite(string $q, int $limit = 15): array {
        $q = trim($q);
        if ($q === '') return [];
        $limit = max(1, min(50, $limit));
        $like = '%' . $q . '%';
        return Database::fetchAll(
            "SELECT mb.id, mb.booking_code, mb.passenger_name, mb.passport_number, mb.flight_number,
                    mb.flight_itinerary_json, mb.gender, mb.pax_type, mb.arrival_date, mb.departure_date,
                    a.name AS agent_name, mb.agent_id
             FROM master_bookings mb
             LEFT JOIN agents a ON a.id = mb.agent_id
             WHERE mb.deleted_at IS NULL
               AND (mb.passenger_name LIKE ? OR mb.booking_code LIKE ? OR mb.passport_number LIKE ? OR mb.flight_number LIKE ?)
             ORDER BY mb.booking_date DESC, mb.id DESC
             LIMIT {$limit}",
            [$like, $like, $like, $like]
        );
    }

    public static function getAll(array $filters=[],int $limit=50,int $offset=0):array {
        $conditions=['mb.deleted_at IS NULL'];$params=[];
        if(!empty($filters['agent_id'])){$conditions[]='mb.agent_id=?';$params[]=(int)$filters['agent_id'];}
        if(!empty($filters['vendor_id'])){$conditions[]='mb.vendor_id=?';$params[]=(int)$filters['vendor_id'];}
        if(!empty($filters['status'])){$conditions[]='mb.status=?';$params[]=$filters['status'];}
        if(!empty($filters['search'])){$q='%'.trim($filters['search']).'%';$conditions[]='(mb.booking_code LIKE ? OR mb.passenger_name LIKE ? OR mb.passport_number LIKE ? OR mb.flight_number LIKE ?)';array_push($params,$q,$q,$q,$q);}
        if(!empty($filters['date_from'])){$conditions[]='mb.booking_date>=?';$params[]=$filters['date_from'];}
        if(!empty($filters['date_to'])){$conditions[]='mb.booking_date<=?';$params[]=$filters['date_to'];}
        if(!empty($filters['month'])){$conditions[]="DATE_FORMAT(mb.booking_date,'%Y-%m')=?";$params[]=$filters['month'];}
        if(!empty($filters['year'])){$conditions[]='YEAR(mb.booking_date)=?';$params[]=(int)$filters['year'];}
        $limit=max(1,min(500,(int)$limit));$offset=max(0,(int)$offset);$where='WHERE '.implode(' AND ',$conditions);
        $rows = Database::fetchAll("SELECT mb.*,a.name AS agent_name,v.name AS vendor_name,(SELECT COUNT(*) FROM transport_transfers tt WHERE tt.booking_id=mb.id AND tt.deleted_at IS NULL) AS has_transport,(SELECT COUNT(*) FROM hotel_stays hs WHERE hs.booking_id=mb.id AND hs.deleted_at IS NULL) AS has_hotel FROM master_bookings mb LEFT JOIN agents a ON a.id=mb.agent_id LEFT JOIN vendors v ON v.id=mb.vendor_id {$where} ORDER BY mb.booking_date DESC,mb.id DESC LIMIT {$limit} OFFSET {$offset}",$params);
        foreach ($rows as &$row) { $row['attachments'] = BookingFileController::getCurrentWithUrls((int)$row['id']); }
        unset($row);
        return $rows;
    }

    public static function delete(int $id): array {
        if($id<=0)return ['success'=>false,'message'=>'Invalid booking ID.'];$booking=Database::fetchOne("SELECT * FROM master_bookings WHERE id=? AND deleted_at IS NULL",[$id]);if(!$booking)return ['success'=>false,'message'=>'Booking not found.'];
        Database::beginTransaction();try{$before=self::auditSnapshot($id);$actor=self::actor();Database::execute("UPDATE master_bookings SET deleted_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=?",[$actor,$id]);Database::execute("UPDATE hotel_stays SET deleted_at=NOW(),updated_by=?,updated_at=NOW() WHERE booking_id=? AND deleted_at IS NULL",[$actor,$id]);Database::execute("UPDATE transport_transfers SET deleted_at=NOW(),updated_by=?,updated_at=NOW() WHERE booking_id=? AND deleted_at IS NULL",[$actor,$id]);Database::execute("UPDATE booking_files SET deleted_at=NOW(),updated_by=?,updated_at=NOW() WHERE booking_id=? AND deleted_at IS NULL",[$actor,$id]);self::audit($id,'deleted',$before,null);Database::commit();self::removeStoredFiles($id);return ['success'=>true,'message'=>'Booking record deleted successfully.'];}catch(Throwable $e){Database::rollBack();return ['success'=>false,'message'=>'Delete failed: '.$e->getMessage()];}
    }

    private static function removeStoredFiles(int $bookingId):void{$files=Database::fetchAll("SELECT storage_path FROM booking_files WHERE booking_id=?",[$bookingId]);foreach($files as $f){$p=__DIR__.'/../'.ltrim((string)$f['storage_path'],'/');if(is_file($p))@unlink($p);}}
    private static function audit(int $id,string $action,?array $old,?array $new):void{Database::execute("INSERT INTO audit_logs(entity_type,entity_id,action,old_values,new_values,created_by,created_at) VALUES('master_booking',?,?,?,?,?,NOW())",[$id,$action,$old?json_encode($old,JSON_UNESCAPED_UNICODE):null,$new?json_encode($new,JSON_UNESCAPED_UNICODE):null,self::actor()]);}
    private static function auditSnapshot(int $id):array{$b=Database::fetchOne("SELECT * FROM master_bookings WHERE id=?",[$id])?:[];$b['hotel_stays']=Database::fetchAll("SELECT * FROM hotel_stays WHERE booking_id=? AND deleted_at IS NULL ORDER BY sequence,id",[$id]);$b['transport_transfers']=Database::fetchAll("SELECT * FROM transport_transfers WHERE booking_id=? AND deleted_at IS NULL ORDER BY sequence,id",[$id]);return $b;}
    private static function getBookingCode(int $id):string{return (string)(Database::fetchValue("SELECT booking_code FROM master_bookings WHERE id=?",[$id])?:$id);}
    private static function generateBookingCode():string{do{$c='AHT-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));$e=Database::fetchValue("SELECT id FROM master_bookings WHERE booking_code=?",[$c]);}while($e);return $c;}
    private static function money(mixed $v):float{$v=str_replace([',',' '],'',(string)$v);return max(0,round((float)(is_numeric($v)?$v:0),2));}
    private static function status(mixed $v):string{$v=strtolower(trim((string)$v));return in_array($v,['draft','confirmed','issued','completed','cancelled'],true)?$v:'draft';}
    private static function dateOrNull(mixed $v):?string{$v=trim((string)$v);if($v==='')return null;foreach(['Y-m-d','d/m/Y','d-m-Y','Y/m/d'] as $f){$d=DateTimeImmutable::createFromFormat('!'.$f,$v);if($d&&$d->format($f)===$v)return $d->format('Y-m-d');}return null;}
    private static function timeOrNull(mixed $v):?string{$v=trim((string)$v);if($v==='')return null;if(preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$v))return strlen($v)===5?$v.':00':$v;return null;}
}
