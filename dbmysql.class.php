<?php 
    class DBMySQL
    {
        public $TablePrefix;    

        protected $DB;
        protected $DBName; 

        protected $HostName;
        protected $UserName;
        protected $Password;   


        static public function GetDB()
        {
            if (is_null(Controller::$DB) or get_class(Controller::$DB) != 'DBMySQL')
            {
                new DBMySQL;
            }
            return Controller::$DB;
        }

        /**
        * Создает объект для работы с MySQL БД, производит подключение к серверу и выбор БД
        */
        protected function __construct($Host = '', $User = '', $Password = '', $DBName = '', $Prefix = '') 
        {

            $this->HostName = DB_Host;
            $this->UserName = DB_UserName;
            $this->Password = DB_Password;
            $this->DBName = DB_Name;
            $this->TablePrefix = DB_TablePrefix;

            $Hahdler = @mysql_pconnect($this->HostName, $this->UserName, $this->Password);

            if (!$Hahdler)                                 
            {
                ErrorHandle::Handle('Ошибка при подключении к серверу MySQL.');
                $this->DB = false;
                Controller::$DB = false;
            }
            elseif (!mysql_select_db($this->DBName, $Hahdler))
            {
                ErrorHandle::Handle('Ошибка при подключении к базе данных.');
                $this->DB = false;
                Controller::$DB = false;
            }
            else 
            {
                Controller::$DB = $this;
                $this->DB = $Hahdler;
            }

        }

        public function __toString() 
        {
            return $this->DB;
        }

        public function Query($Sql)
        {
            if ($DB = Controller::$DB)
            {   
                if (!$Result = mysql_query($Sql,$DB->DB)) 
                {
                    ErrorHandle::Handle('Ошибка при выполнении запроса.');
                    ErrorHandle::Handle(mysql_error($DB->DB));
                }
                return $Result;
            }
            else return false;
        }

        public function InsertID()
        {
            if ($DB = Controller::$DB)
            {   
                $Id = mysql_insert_id($DB->DB);   
                if ($Id === false)
                {
                    ErrorHandle::Handle('Ошибка при получении идентификатора созданой записи.');
                }
                elseif ($Id === 0)
                {
                    ErrorHandle::Handle('Новый идентификатор не создан, но производится попытка его получения.');
                }
                return $Id;
            }
            else return false;
        }

        public function CountRows($Result)
        {
            if ($Result)
            {   
                $Num = mysql_num_rows($Result);   
                if ($Num === false)
                {
                    ErrorHandle::Handle('Ошибка при вычислении количество строк в наборе данных.');
                }
                return $Num;
            }
            else return false;
        }

        public function FetchObject($Result)
        {     
            return $Result?mysql_fetch_object($Result):false; 
        } 

        public function FetchArray($Result)
        {
            return $Result?mysql_fetch_assoc($Result):false; 
        }

        public function Ping()
        {
            return Controller::$DB?mysql_ping(Controller::$DB):false;
        }
    }     
?>
