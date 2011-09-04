<?php
    class Entity extends BaseClass
    {
        public static   $Cashable       = true;
        
        public static   $UnAuthUse      = true;

        public          $ChangedFields  = array();
        
        public static   $UnAuthActions  = array();
        public static   $Actions 		= array(
        'save'=>'SaveAction',
        'delete'=>'DeleteAction'
        );


        public static   $Forms = array();
        public static   $UnAuthForms      = array();

        public $SQLFields = array(
        'ID' => 'ID',
        'Description' => 'DESCRIPTION'
        );

        protected       $DBTableName;

        public $ID = null;
        public $ParentID = null;
        public $OwnerID = null;
        public $OwnerClass = 'Entity';
        public $Description = '';
        public $Code = '';

        protected $Modified = false;
        protected $SystemMark = false;

        static public function GetSQLField($Field)
        {
            return  $this->SQLFields[$Field];
        }

        protected function GetSQLValue($FieldName)
        {
            $value = trim($this->$FieldName);
            if (!isset($value) or is_null($value) or $value == '') $result = 'null';
            else $result = '"'.addslashes(mysql_real_escape_string($value)).'"';
            return $result;
        }

        protected function Refresh()
        {
            if ($DB = Controller::$DB and intval($this->ID))
            {
                $this->Modified = false;
                $sql = 'Select * from '.$this->DBTableName.' where ID = '.$this->ID.' LIMIT 1';

                $hSql = $DB->Query($sql);
                if ($fetch = $DB->FetchObject($hSql)) 
                    foreach ($this->SQLFields as $Attr => $Field) 
                        $this->$Attr = stripcslashes($fetch->$Field);
            }
        }

        public function Save()
        {
            $Result = false;

            if (!$DB = Controller::$DB)
                ErrorHandle::ActionErrorHandle('Отсутствует подключение к базе данных.Сохранение объекта невозможно.',3);

            elseif (intval($this->ID) ==0)
            {
                $FieldList = '';
                $ValList = '';
                foreach ($this->SQLFields as $ObjectField => $DBField)
                {
                    $FieldList = $FieldList.($FieldList ==''?'':',')."`".$DBField."`";
                    $ValList = $ValList.($ValList ==''?'':',').$this->GetSQLValue($ObjectField);
                }
                
                ErrorHandle::ActionErrorHandle($sql = 'insert into '.$this->DBTableName.' ('.$FieldList.') values ('.$ValList.')');

                if ($hSql = $DB->Query($sql))
                {
                    $this->ID = $DB->InsertID($hSql);
                    $this->ChangedFields[] = array('name' => 'ID','value' => $this->ID);
                    ErrorHandle::ActionErrorHandle('Объект типа '.get_class($this).' успешно сохранен.',0);
                    $Result = true;
                }
                else
                    ErrorHandle::ActionErrorHandle('Ошибка сохранения объекта типа '.get_class($this).'.',2);
            }
            else
            {
                $FieldList = '';
                foreach ($this->SQLFields as $ObjectField => $DBField)
                {
                    $FieldList = $FieldList.($FieldList ==''?'':',')."`".$DBField."`"."=".$this->GetSQLValue($ObjectField);
                }
                ErrorHandle::ActionErrorHandle($sql = 'update '.$this->DBTableName.' set '.$FieldList.' where ID = '.$this->ID);
                
                if ($DB->Query($sql))
                {
                    ErrorHandle::ActionErrorHandle('Объект типа '.get_class($this).' успешно сохранен.',0);
                    $Result = true;
                }
                else
                {
                    ErrorHandle::ActionErrorHandle('Ошибка сохранения объекта типа '.get_class($this).'.',2);
                    
                }
            }
            return $Result;
        }         

        public function Delete()
        {
            $Result = false;
            $sql = 'delete from '.$this->DBTableName.' where ID = '.intval($this->ID);

            if (!Controller::$DB)
                ErrorHandle::ActionErrorHandle('Отсутствует подключение к базе данных.Удаление объекта типа '.get_class($this).' невозможно.',3);

            elseif (!intval($this->ID))
                ErrorHandle::ActionErrorHandle('Не указан идентификатор удаляемого объекта типа '.get_class($this).'.',1);

            elseif ($this->SystemMark)
                ErrorHandle::ActionErrorHandle('Попытка удаления системного объекта типа '.get_class($this).'.',1);

            elseif (!$hSql = Controller::$DB->Query($sql))
                ErrorHandle::ActionErrorHandle('Ошибка удаления объекта типа '.get_class($this).'.',3);

            else
            {
                ErrorHandle::ActionErrorHandle('Объект типа '.get_class($this).' успешно удален.',0);
                $Result = true;
            }
            return $Result;
        }

        protected function SetActionData()
        {
            if (isset($this->ProcessData['Description']))
            {
                $this->Description = $this->ProcessData['Description'];
                $this->Modified = true;
            }

            if (isset($this->ProcessData['OwnerID']) and intval($this->ProcessData['OwnerID']) and ($this->ProcessData['OwnerID'] != $this->OwnerID))
            {
                $this->OwnerID = intval($this->ProcessData['OwnerID']);
                $this->Modified = true;
            }

            if (isset($this->ProcessData['ParentID']) and intval($this->ProcessData['ParentID']) and ($this->ProcessData['ParentID'] != $this->ParentID))
            {

                $this->ParentID = intval($this->ProcessData['ParentID']);
                $this->Modified = true;
            }

            if (isset($this->ProcessData['Code']) and ($this->ProcessData['Code'] != $this->Code))
            {
                $this->Code = $this->ProcessData['Code'];
                $this->Modified = true;
            }

            foreach ($this->SQLFields as $Attr => $Field)
            {
                if (isset($this->ProcessData[$Attr]) and ($this->ProcessData[$Attr] != $this->$Attr))
                {
                    $this->$Attr = $this->ProcessData[$Attr];
                    $this->Modified = true;
                }
            }

            return $this->Modified;
        }

        public function SaveAction()
        {
            $this->Refresh();
            $result = $this->SetActionData() and $this->Save(); 
            return $result;
        }

        public function DeleteAction()
        {
            return $this->Delete();
        }

        protected function __construct(&$ProcessData,$ID=null)  
        {   
            parent::__construct($ProcessData,$ID);
            $this->Refresh();
            if (is_null($this->ID) )
            {
                $this->SetActionData();    
            }
        }      

        public function __toString()
        {
            return $this->Description;
        }

        public function __get($FieldName)
        {
            $null = null;
            switch (strtolower($FieldName))
            {
                case 'parent' : 
                {
                    if (!intval($this->ParentID)) $result = null;
                    else
                    {                                       
                        $ClassName = get_class($this); 
                        $result = call_user_func_array(array($ClassName,'GetObject'),array(&$null,&$this->ParentID));  
                        break;
                    }
                }
                case 'owner' : 
                { 
                    if (!intval($this->OwnerID))                $result = null;
                    elseif (!class_exists($this->OwnerClass))   $result = null;
                    else
                    {
                        $ClassName = $this->OwnerClass; 
                        $result = call_user_func_array(array('ClassName','GetObject'),array(&$null,&$this->OwnerID));  
                    }
                    break;
                }
                case 'topparent'    :
                {
                    if (!intval($this->ParentID)) $result = $this;
                    else $result = $this->Parent->TopParent;
                    break;
                }
                default : $result = parent::__get($FieldName);
            }
            
            return $result;
        }
    }
?>
