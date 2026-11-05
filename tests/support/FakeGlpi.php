<?php
declare(strict_types=1);
/** Deliberately small GLPI test doubles. These do not certify native GLPI or MariaDB behavior. */
const READ=1; const UPDATE=2; const CREATE=4; const ERROR=1;
final class FakeDb
{
    public array $tables=[];private array $snapshots=[];private int $last=0;
    public function request(array $query): ArrayIterator {
        $rows=array_values(array_filter($this->tables[$query['FROM']]??[],fn($r)=>$this->matches($r,$query['WHERE']??[])));
        if(isset($query['ORDER']))foreach(array_reverse((array)$query['ORDER']) as $order){
            [$field,$direction]=array_pad(explode(' ',$order),2,'ASC');
            usort($rows,fn($a,$b)=>($direction==='DESC'?-1:1)*(($a[$field]??null)<=>($b[$field]??null)));
        }
        if(isset($query['LIMIT']))$rows=array_slice($rows,(int)($query['START']??0),(int)$query['LIMIT']);
        return new ArrayIterator($rows);
    }
    private function matches(array $row,array $where):bool {
        foreach($where as $key=>$value){
            if(is_array($value)){
                if(($value[0]??null)==='>'){if(($row[$key]??0)<=$value[1])return false;}
                elseif(!in_array($row[$key]??null,$value,false))return false;
            }elseif(($row[$key]??null)!=$value)return false;
        }return true;
    }
    public function insert(string $table,array $data):bool {
        if(str_ends_with($table,'_templates'))$data+=['active_versions_id'=>null];
        if(str_ends_with($table,'_stepinstances'))$data+=['started_at'=>null,'started_by'=>null,'completed_at'=>null,'completed_by'=>null];
        $rows=$this->tables[$table]??[];$id=$rows?max(array_keys($rows))+1:1;
        $this->tables[$table][$id]=$data+['id'=>$id];$this->last=$id;return true;
    }
    public function insertId():int{return $this->last;}
    public function update(string $table,array $data,array $where):bool {
        foreach($this->tables[$table]??[] as $id=>$row)if($this->matches($row,$where))$this->tables[$table][$id]=array_replace($row,$data);return true;
    }
    public function updateOrInsert(string $table,array $data,array $where):bool {
        foreach($this->tables[$table]??[] as $row)if($this->matches($row,$where))return $this->update($table,$data,$where);
        return $this->insert($table,$where+$data);
    }
    public function beginTransaction():void{$this->snapshots[]=$this->tables;}
    public function commit():void{array_pop($this->snapshots);}
    public function rollBack():void{$this->tables=array_pop($this->snapshots);}
    public function doQuery(string $sql):array {
        if(str_contains($sql,'GET_LOCK'))return ['acquired'=>1];
        return [];
    }
    public function fetchAssoc(array $result):array{return $result;}
    public function tableExists(string $name):bool{return isset($this->tables[$name]);}
}
final class Session
{
    public static array $rights=[];public static array $messages=[];public static int $user=7;
    public static function haveRight(string $name,int $bit):bool{return ((self::$rights[$name]??0)&$bit)===$bit;}
    public static function haveAccessToEntity($id,$recursive=false):bool{return (int)$id===0;}
    public static function getCurrentTime(): ?string { return date('Y-m-d H:i:s'); }
    public static function getLoginUserID():int{return self::$user;}
    public static function checkLoginUser():void{if(self::$user<=0)throw new \WorkflowBuilder\ValidationException('Session requise.');}
    public static function addMessageAfterRedirect($message,$unused=false,$type=0):void{self::$messages[]=$message;}
}
function getAncestorsOf($table,$id):array{return [];}
function getUserName($id):string{return (int)$id===7?'Technicien A':'Technicien B';}
class CommonITILObject {public const INCOMING=1;public const SOLVED=5;public const CLOSED=6;}
final class Ticket extends CommonITILObject
{
    public array $fields=[];public array|false $input=[];
    public static function getFormURLWithID($id):string{return '/front/ticket.form.php?id='.$id;}
    public function getID():int{return (int)($this->fields['id']??0);}
    public function can($id,int $right,?array &$input=null):bool {
        global $DB;
        if(!$id)return Session::haveRight('native_ticket',CREATE);
        $row=$DB->tables['glpi_tickets'][$id]??null;
        if(!$row||!Session::haveRight('native_ticket',$right)||!Session::haveAccessToEntity($row['entities_id']))return false;
        $this->fields=$row;return true;
    }
    public function canAssign():bool{return Session::haveRight('native_assign', UPDATE);}
    public function add(array $input):int {
        global $DB;
        $DB->insert('glpi_tickets',$input+['is_deleted'=>0]);
        $this->fields=$DB->tables['glpi_tickets'][$DB->insertId()];return $DB->insertId();
    }
}
final class ITILFollowup
{
    public static bool $failNext = false;
    public function can($id, int $right, ?array &$input = null): bool { return Session::haveRight('native_followup', $right); }
    public function add(array $input): int|false {
        global $DB;
        if (self::$failNext) { self::$failNext = false; return false; }
        $DB->insert('glpi_itilfollowups', $input);
        return $DB->insertId();
    }
}
class Reference
{
    public array $fields=[];
    public function can($id,int $right):bool {
        if((int)$id===404)return false;
        $this->fields=['id'=>$id,'name'=>'Reference','is_active'=>1,'is_deleted'=>0];return true;
    }
}
class User extends Reference {public static function isUserReauthenticationNeeded():bool{return false;}} class Group extends Reference {} class Entity extends Reference {} class Computer extends Reference {}
class KnowbaseItem extends Reference {}
class Document extends Reference
{
    public function getFromDB($id):bool{return $this->can($id,READ);}
    public function canViewFile(array $options=[]):bool{return ($this->fields['id']??404)!==404;}
}
