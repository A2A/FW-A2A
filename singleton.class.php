<?php 
    abstract class Singleton
    {
        private static  $Cash;
        protected       $ProcessData;
        public static   $Cashable = false;

        public static function GetStaticProperty($ClassName,$PropertyName)
        {
            return ($VarArr = get_class_vars($ClassName))?$VarArr[$PropertyName]:null;
        }

        protected function __construct(&$ProcessData,$id=null)  
        {   
            $this->ProcessData = &$ProcessData;

            if (property_exists(get_class($this),'ID'))
            {
                if (intval($id)>0) $this->ID = $id;
                elseif (isset($ProcessData['ID']) and intval($ProcessData['ID'])>0) $this->ID = $ProcessData['ID'];
            }
        }    

        public final static function GetObjectInstance(&$ProcessData,$id,$ClassName)
        {
            if (property_exists($ClassName,'ID'))
            {
                if (intval($id)) $TmpID = intval($id);
                elseif (isset($ProcessData['ID']) and intval($ProcessData['ID'])) $TmpID = intval($ProcessData['ID']);
                else $TmpID = null;

                if (is_null($TmpID)  or (!self::GetStaticProperty($ClassName,'Cashable')))
                {
                    $Result = new $ClassName($ProcessData,$TmpID);
                }
                elseif ((!isset(Singleton::$Cash[$ClassName])) or (!isset(Singleton::$Cash[$ClassName][$TmpID])))
                {
                    $Result = new $ClassName($ProcessData,$TmpID); 
                    $ClVars = get_class_vars($ClassName);
                    if ($ClVars['Cashable']) Singleton::$Cash[$ClassName][$TmpID] = &$Result;
                }
                else
                {
                    $Result = Singleton::$Cash[$ClassName][$TmpID];
                }
            }
            elseif  (self::GetStaticProperty($ClassName,'Cashable'))
            {
                if ((!isset(Singleton::$Cash[$ClassName])) or (!isset(Singleton::$Cash[$ClassName][null])))
                {
                    $Result = new $ClassName($ProcessData);                    
                    Singleton::$Cash[$ClassName][null] = &$Result;
                }
                else
                {
                    $Result = Singleton::$Cash[$ClassName][null];
                }
            }
            else
            {
                $Result = new $ClassName($ProcessData);
            }
            return $Result ;
        }

        public static function GetObject(&$ProcessData,$id=null)
        {
            return self::GetObjectInstance($ProcessData,$id,__CLASS__);
        }
        
        public function __get($FieldName)
        {
            ErrorHandle::Handle('Попытка обращения к несуществующему полю '.$FieldName.' объекта класса '.get_class($this),2);
            return null;
        }
        

    }

?>
