<?php
declare(strict_types=1);
/** Isolated real MariaDB schema tests. Creates/drops ONLY a newly generated test database. */
require dirname(__DIR__).'/bootstrap.php';
use WorkflowBuilder\{Schema,Store};
if(getenv('WORKFLOWBUILDER_ALLOW_DB_TESTS')!=='1'){fwrite(STDERR,"Set WORKFLOWBUILDER_ALLOW_DB_TESTS=1 on a dedicated test server.\n");exit(2);}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli(getenv('WB_DB_HOST')?:'127.0.0.1',getenv('WB_DB_USER')?:'root',getenv('WB_DB_PASSWORD')?:'',null,(int)(getenv('WB_DB_PORT')?:3306));
$name='workflowbuilder_test_'.bin2hex(random_bytes(8));
$db->query('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->select_db($name);
try {
    for($pass=0;$pass<2;$pass++)foreach(Schema::definitions() as $table=>$columns){
        $db->query('CREATE TABLE IF NOT EXISTS '.Store::table($table).' (id int unsigned NOT NULL AUTO_INCREMENT, '.$columns.', PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    $count=$db->query('SHOW TABLES')->num_rows;
    if($count!==10)throw new RuntimeException('Expected 10 plugin tables');
    echo "PASS schema creates 10 tables; repeat installation preserves schema\n";
    $db->query("INSERT INTO ".Store::table('templates')." (name,description,users_id,draft,date_creation,date_mod) VALUES ('Test','',1,'{}',NOW(),NOW())");
    $db->query("INSERT INTO ".Store::table('versions')." (templates_id,number,definition,checksum,users_id,date_creation) VALUES (1,1,'{}',REPEAT('a',64),1,NOW())");
    $db->query("INSERT INTO ".Store::table('instances')." (templates_id,versions_id,tickets_id,entities_id,users_id,request_key,status,date_creation,date_mod) VALUES (1,1,1,0,1,REPEAT('b',64),'running',NOW(),NOW())");
    try {
        $db->query("INSERT INTO ".Store::table('instances')." (templates_id,versions_id,tickets_id,entities_id,users_id,request_key,status,date_creation,date_mod) VALUES (1,1,1,0,1,REPEAT('b',64),'running',NOW(),NOW())");
        throw new RuntimeException('Unique launch key was not enforced');
    } catch(mysqli_sql_exception $e) {if($e->getCode()!==1062)throw $e;}
    echo "PASS unique launch request key enforced by MariaDB\n";
    $db->begin_transaction();
    $db->query("UPDATE ".Store::table('instances')." SET status='completed' WHERE id=1");
    $db->rollback();
    if($db->query('SELECT status FROM '.Store::table('instances').' WHERE id=1')->fetch_assoc()['status']!=='running')throw new RuntimeException('Rollback failed');
    echo "PASS InnoDB rollback preserves instance state\n";
    $payload=json_encode(['label'=>"Texte d’essai ' <script>","data"=>str_repeat('é',40000)],JSON_UNESCAPED_UNICODE);
    $stmt=$db->prepare('INSERT INTO '.Store::table('fields').' (versions_id,step_key,field_key,position,definition) VALUES (1,?,?,0,?)');
    $step='';$key='sample';$stmt->bind_param('sss',$step,$key,$payload);$stmt->execute();
    if($db->query('SELECT definition FROM '.Store::table('fields'))->fetch_assoc()['definition']!==$payload)throw new RuntimeException('UTF8 roundtrip failed');
    echo "PASS large UTF8 field definitions roundtrip\n";
    $db->query("SELECT GET_LOCK('workflowbuilder_schema_test',1)");
    $db->query("SELECT RELEASE_LOCK('workflowbuilder_schema_test')");
    echo "PASS MariaDB advisory locks available\n";
    $table=Store::table('stepinstances');
    $db->query("INSERT INTO ".$table." (instances_id,step_key,state,started_at) VALUES (1,'legacy','progress',NOW())");
    $before=$db->query('SELECT * FROM '.$table)->fetch_assoc();
    if($before['started_by']!==null)throw new RuntimeException('A new step must be unclaimed');
    echo "PASS new ownership column defaults to unclaimed\n";
    $db->query('ALTER TABLE '.$table.' DROP COLUMN started_by');
    $DB=new class($db) {
        public function __construct(private mysqli $db){}
        public function doQuery(string $sql):mixed{return $this->db->query($sql);}
        public function fieldExists(string $table,string $field):bool{return $this->db->query('SHOW COLUMNS FROM '.$table." LIKE '".$this->db->real_escape_string($field)."'")->num_rows>0;}
    };
    $upgrade=require dirname(__DIR__).'/migrations/3.php';$upgrade();$upgrade();
    $after=$db->query('SELECT * FROM '.$table)->fetch_assoc();
    if($after['state']!==$before['state']||$after['started_at']!==$before['started_at']||$after['started_by']!==null)throw new RuntimeException('Ownership migration changed legacy data');
    echo "PASS migration 2 to 3 preserves rows and can be repeated\n";
    $instances=Store::table('instances');
    $oldInstance=$db->query('SELECT * FROM '.$instances.' WHERE id=1')->fetch_assoc();
    $db->query('ALTER TABLE '.$instances.' DROP COLUMN assigned_users, DROP COLUMN assigned_groups');
    $upgrade=require dirname(__DIR__).'/migrations/4.php';$upgrade();$upgrade();
    $newInstance=$db->query('SELECT * FROM '.$instances.' WHERE id=1')->fetch_assoc();
    if($newInstance['request_key']!==$oldInstance['request_key']||$newInstance['status']!==$oldInstance['status']
        ||$newInstance['assigned_users']!==null||$newInstance['assigned_groups']!==null)throw new RuntimeException('Assignment migration changed existing workflows');
    echo "PASS migration 3 to 4 preserves instances and can be repeated\n";

    foreach(array_reverse(Store::TABLES) as $table)$db->query('DROP TABLE IF EXISTS '.Store::table($table));
    if($db->query('SHOW TABLES')->num_rows!==0)throw new RuntimeException('Uninstall did not drop tables');
    echo "PASS all plugin tables removable\n";
} finally {
    $db->select_db('mysql');
    if(!preg_match('/^workflowbuilder_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Invalid test database name');
    $db->query('DROP DATABASE '.$name);
    $db->close();
}
