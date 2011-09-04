<?php
    class CollectionDB extends CollectionBasic
    {
        protected $DBTableName;

        protected function CreateQueryFilter($FilterRec)
        {
            $Condition = '';
            if (isset($FilterRec['Oper']) and array_key_exists ('Val',$FilterRec) and isset($FilterRec['Field']))
            {
                $ClassName = $this->_ValueType;
                if (isset($FilterRec['Val']))
                {
                    if      (!isset($_GET['Enc']))  $Val = $FilterRec['Val'];
                    elseif  ($_GET['Enc'] == 'Рус') $Val = $FilterRec['Val']; 
                    else                            $Val = (iconv("windows-1251","UTF-8",  $FilterRec['Val']));

                    $Val = mysql_real_escape_string(urldecode($Val));
                }
                else
                    $Val = null;

                if ($SQLField = $this->SQLFields[$FilterRec['Field']])
                {
                    if (is_null($Val) or (trim($Val)===''))
                    {
                        switch ($FilterRec['Oper'])
                        {
                            case 'eq': $Condition = ' '.$SQLField. ' IS NULL ' ; break;
                            case '!eq': $Condition = ' '.$SQLField. ' IS NOT NULL '; break;
                            case 'lt': $Condition = '  false ' ; break;
                            case '!lt': $Condition = '  false ' ; break;
                            case '!lt': $Condition = '  false ' ; break;
                            case 'gt': $Condition = '  false ' ; break;
                            case '!gt': $Condition = '  false ' ; break;
                            case 'like': $Condition = ' true ' ; break;
                            case '!like': $Condition = ' false ' ; break;
                            default : $Condition = '';
                        }
                    }
                    else
                    {
                        switch ($FilterRec['Oper'])
                        {
                            case 'eq': $Condition = ' '.$SQLField. ' = "'.$Val.'" ' ; break;
                            case '!eq': $Condition = ' '.$SQLField. ' != "'.$Val.'" ' ; break;
                            case 'lt': $Condition = ' '.$SQLField. ' < "'.$Val.'" ' ; break;
                            case '!lt': $Condition = ' '.$SQLField. ' >= "'.$Val.'" ' ; break;
                            case '!lt': $Condition = ' '.$SQLField. ' >= "'.$Val.'" ' ; break;
                            case 'gt': $Condition = ' '.$SQLField. ' > "'.$Val.'" ' ; break;
                            case '!gt': $Condition = ' '.$SQLField. ' <= "'.$Val.'" ' ; break;
                            case 'like': $Condition = 'UPPER(CAST( '.$SQLField. ' as char)) like UPPER( "%'.$Val.'%")'; break;
                            case '!like': $Condition = 'UPPER(CAST( '.$SQLField. ' as char)) not like UPPER( "%'.$Val.'%")';; break;
                            default : $Condition = '';
                        }
                    }

                } 
            }            
            return $Condition;

        }

        protected function Refresh()
        {

            $null = null;
            $sql = 'Select ID from '.$this->DBTableName;
            if (isset($this->ProcessData['Filter']) and is_array($this->ProcessData['Filter']))
            {
                $Conditions = '';
                foreach ($this->ProcessData['Filter'] as $FilterRec)
                {
                    $Conditions = $Conditions.($Conditions==''?'':' and ').$this->CreateQueryFilter($FilterRec); 
                }
                if ($Conditions != '') $sql .= ' where '.$Conditions; 
            }

            if (Controller::$DB)
            {
                $hSql = Controller::$DB->Query($sql);
                if ($hSql)
                {
                    while ($fetch = Controller::$DB->FetchObject($hSql)) 
                    {
                        $ClassName = $this->_ValueType;
                        $this->add(call_user_func_array(array($ClassName,'GetObject'), array(&$null,$fetch->ID)));
                    }
                }
                else
                {
                    ErrorHandle::Handle('Ошибка выполнения SQL запроса.',1);
                }
            }
            else
            {
                ErrorHandle::Handle('Отсутствует подключение к базе данных.',1);
            }
        }

    }
?>
