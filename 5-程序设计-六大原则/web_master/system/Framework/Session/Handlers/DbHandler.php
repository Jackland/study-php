<?php

namespace Framework\Session\Handlers;

use Illuminate\Database\ConnectionInterface;

class DbHandler implements SessionHandlerInterface
{
    /**
     * @var ConnectionInterface
     */
    private $db;
    /**
     * @var string
     */
    private $table;
    /**
     * @var int
     */
    private $maxLifetime;

    public function __construct(ConnectionInterface $db, $options = [])
    {
        $this->db = $db;
        $this->table = DB_PREFIX . 'session';

        if (isset($options['ttl']) && $options['ttl'] > 0) {
            $this->maxLifetime = (int)$options['ttl'];
        } else {
            $this->maxLifetime = (int)ini_get('session.gc_maxlifetime');
        }
    }

    /**
     * @inheritDoc
     */
    public function read($sessionId)
    {
        $data = $this->db->table($this->table)
            ->where('session_id', $sessionId)
            ->where('expire', '>', time())
            ->first(['data']);
        if ($data) {
            return json_decode($data->data, true) ?: [];
        }
        return [];
    }

    /**
     * @inheritDoc
     */
    public function write($sessionId, $data)
    {
        if ($sessionId) {
            $this->db->statement("INSERT INTO `oc_session` (`session_id`, `data`, `expire`) VALUES (?, ?, ?)" .
                "ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `expire` = VALUES(`expire`)", [
                $sessionId, json_encode($data), $this->getExpire()
            ]);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function destroy($sessionId)
    {
        $this->db->table($this->table)
            ->where('session_id', $sessionId)
            ->delete();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function gc($expire)
    {
        $this->db->table($this->table)
            ->where('expire', '<', $this->getExpire())
            ->delete();

        return true;
    }

    /**
     * @return string
     */
    private function getExpire()
    {
        return date('Y-m-d H:i:s', time() + $this->maxLifetime);
    }
}
