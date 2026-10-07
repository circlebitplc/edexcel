<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

final class ServiceRequestService
{
    public function __construct(private PDO $pdo) {}

    public function create(string $requesterType,int $requesterId,string $requestType,array $data):int
    {$ticket=(new SupportTicketService($this->pdo))->create($requesterType,$requesterId,['category'=>$requestType,'subject'=>$data['subject']??ucwords(str_replace('_',' ',$requestType)),'description'=>$data['description']??'','priority'=>$data['priority']??'normal']);$this->pdo->prepare('INSERT INTO service_request_links(ticket_id,request_type,entity_type,entity_id,lifecycle_json) VALUES(?,?,?,?,?)')->execute([$ticket,$requestType,$data['entity_type']??null,$data['entity_id']??null,json_encode(['created'=>date('c'),'state'=>'open'])]);return$ticket;}

    /** @return list<array<string,mixed>> */
    public function list(string $requesterType,int $requesterId,bool $admin=false):array
    {try{$sql="SELECT t.*,l.request_type,l.entity_type,l.entity_id FROM support_tickets t LEFT JOIN service_request_links l ON l.ticket_id=t.id";$p=[];if(!$admin){$sql.=' WHERE t.requester_type=? AND t.requester_id=?';$p=[$requesterType,$requesterId];}$sql.=' ORDER BY t.updated_at DESC,t.created_at DESC';$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable $e){return[];}}
}
