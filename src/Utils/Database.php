<?php

namespace Utils;

use Exception;
use mysqli;
use mysqli_sql_exception;
use Pangine\utils\Exception500;

class Database
{

    private bool $db_was_used = false;

    private mysqli $conn;

    public function __construct()
    {
        $this->conn = new mysqli(getenv('MYSQLHOST'), getenv('MYSQLUSER'), getenv('MYSQL_ROOT_PASSWORD'), getenv('MYSQLDATABASE'));
    }

    public function get_connection(): mysqli {
        return $this->conn;
    }

    /**
     * @throws Exception500
     */
    public function close(): void
    {
        if ($this->conn->ping()) {
            $this->conn->close();
            if(!$this->db_was_used){
                throw new Exception500("DB connection was created but never used.");
            }
        }
    }

    public function __destructor(): void
    {
        $this->close();
    }

    /**
     * @throws Exception500
     */
    public function execute_query(string $query, ...$params): array|bool
    {
        try {
            $stmt = $this->conn->prepare("USE ".getenv("MYSQLDATABASE").";".$query);

            if ($stmt === false) {
                throw new Exception("Errore nella preparazione della query");
            }

            if (!empty($params)) {
                $stmt->bind_param(str_repeat("s", count($params)), ...$params);
            }

            $success = $stmt->execute();
            $res_set = $stmt->get_result();

            if ($res_set !== false) {
                $ret_set = $res_set->fetch_all(MYSQLI_ASSOC);
            } else {
                $ret_set = $success;
            }

            $stmt->close();
        } catch (Exception $ex) {
            throw $ex;
            // teniamo così finché stiamo sviluppando
            //throw new Exception500("Errore di connessione con il database. Si prega di riprovare tra qualche secondo.");
        }
        $this->db_was_used = true;
        return $ret_set;
    }
}
