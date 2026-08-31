<?php
declare(strict_types=1);
final class Audit{
 public static function record(PDO $db,string $module,string $action,?string $entityType=null,string|int|null $entityId=null,?array $before=null,?array $after=null):void{
  $sql='INSERT INTO audit_logs(user_id,module_key,action,entity_type,entity_id,before_data,after_data,ip_address) VALUES(?,?,?,?,?,?,?,?)';
  $db->prepare($sql)->execute([$_SESSION['user']['id']??null,$module,$action,$entityType,$entityId,$before?json_encode($before,JSON_UNESCAPED_UNICODE):null,$after?json_encode($after,JSON_UNESCAPED_UNICODE):null,$_SERVER['REMOTE_ADDR']??null]);
 }
}
