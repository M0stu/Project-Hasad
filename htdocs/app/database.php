<?php
declare(strict_types=1);
final class Db {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (self::$pdo) { return self::$pdo; }
        $d = cfg('db', []);
        if (!$d) { throw new RuntimeException('Database is not configured'); }
        self::$pdo = new PDO('mysql:host='.$d['host'].';port='.($d['port'] ?? 3306).';dbname='.$d['name'].';charset=utf8mb4', $d['user'], $d['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>5]);
        self::$pdo->exec("SET time_zone = '+00:00'");
        return self::$pdo;
    }
    public static function run(string $sql, array $p = []): PDOStatement { $s = self::pdo()->prepare($sql); $s->execute($p); return $s; }
    public static function one(string $sql, array $p = []): ?array { $v = self::run($sql,$p)->fetch(); return $v ?: null; }
    public static function all(string $sql, array $p = []): array { return self::run($sql,$p)->fetchAll(); }
    public static function scalar(string $sql, array $p = []): mixed { return self::run($sql,$p)->fetchColumn(); }
    public static function insert(string $sql, array $p = []): int { self::run($sql,$p); return (int)self::pdo()->lastInsertId(); }
    public static function tx(callable $fn): mixed { self::pdo()->beginTransaction(); try { $v=$fn(); self::pdo()->commit(); return $v; } catch (Throwable $e) { if (self::pdo()->inTransaction()) { self::pdo()->rollBack(); } throw $e; } }
    public static function inTransaction(): bool { return self::$pdo !== null && self::$pdo->inTransaction(); }
    public static function rollback(): void { if (self::inTransaction()) { self::$pdo->rollBack(); } }
}
function audit(string $action, string $entity, int $id, array $details = []): void { Db::run('INSERT INTO audit_log(user_id,action,entity,entity_id,details) VALUES(?,?,?,?,?)', [staff()['id'] ?? member()['id'] ?? null,$action,$entity,$id,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]); }
function rate_limit(string $bucket, int $limit, int $seconds): void {
    $hash=hash_hmac('sha256',$bucket,(string)cfg('app_key'));
    // Named locks serialize attempts across browser sessions and prevent races at the limit.
    $lock='hasad_rl_' . substr($hash,0,40);
    if ((int)Db::scalar('SELECT GET_LOCK(?,3)',[$lock]) !== 1) { throw new DomainException(t('حاول مجدداً بعد قليل.','Please try again shortly.')); }
    try {
        if ((int)Db::scalar('SELECT COUNT(*) FROM rate_events WHERE bucket=? AND created_at > DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$seconds.' SECOND)',[$hash]) >= $limit) { throw new DomainException(t('محاولات كثيرة. حاول مرة أخرى لاحقاً.','Too many attempts. Please try again later.')); }
        Db::run('INSERT INTO rate_events(bucket) VALUES(?)',[$hash]);
        if (random_int(1,100) === 1) { Db::run('DELETE FROM rate_events WHERE created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) LIMIT 2000'); }
    } finally { Db::run('SELECT RELEASE_LOCK(?)',[$lock]); }
}
