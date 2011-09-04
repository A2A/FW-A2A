<?php
    class Controller extends Singleton
    {
        public static  $Cashable = true;
        public static $DB;

        protected function __construct(&$ProcessData)  
        {
            DBMySQL::GetDB();
        }

        public static function CheckActionPermissions($RequestedAction,$ClassName)
        {
            
            $result = false;
            
            if ($_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'])
            {
                $result = true;
            }
            elseif (!isset($_SERVER['HTTP_REFERER']))
            {
                ErrorHandle::ActionErrorHandle('Для осуществления операции необходимо указание реферера. Операция прервана.',2);
            }
            elseif (strpos($_SERVER['HTTP_REFERER'],'http://'.$_SERVER['SERVER_NAME']) !== 0)
            {
                ErrorHandle::ActionErrorHandle('Указан недопустимый реферер. referrer - ['.$_SERVER['HTTP_REFERER'].'] server - ['.'http://'.$_SERVER['SERVER_NAME'].'] ['.$www.'] Операция прервана.',2);
            }
            else
            {
                $result = true;
            }
            return $result;
        }

        public static function CheckFormPermissions($RequestedForm,$ClassName)
        {
            return true;
        }

        public static function CheckClassPermissions($ClassName)
        {
            return true;
        }

        public static function CheckActionAccess($RequestedAction,$ClassName)
        {
            $AccessGranted = true;

            if (!self::CheckClassAccess($ClassName))
            {
                ErrorHandle::Handle('Нарушение прав доступа к объекту класса '.$ClassName.' при обработке события.',3);
                $AccessGranted = false;
            }
            elseif (!(self::IsUserAuthorized()))
            {

                if (($UnAuthActions = self::GetStaticProperty($ClassName,'UnAuthActions')) and $UnAuthActions[$RequestedAction])
                {
                }
                else
                {
                    ErrorHandle::Handle('Нарушение прав доступа к модулю обработки события "'.$RequestedAction.'".',3);
                    $AccessGranted = false;
                }
            }
            else                                           
            {
                $AccessGranted = self::CheckActionPermissions($RequestedAction,$ClassName);
            }

            return $AccessGranted;
        }

        public static function CheckFormAccess($RequestedForm,$ClassName)
        {
            $AccessGranted = true;

            if (!Controller::CheckClassAccess($ClassName))
            {
                ErrorHandle::Handle('Нарушение прав доступа к объекту класса '.$ClassName.' при построении отображения.',3);
                $AccessGranted = false;
            }
            elseif (!self::IsUserAuthorized())
            {
                if ($UnAuthForms = self::GetStaticProperty($ClassName,'UnAuthForms') and isset($UnAuthForms[$RequestedForm])  and $UnAuthForms[$RequestedForm])
                {
                    // доступ есть
                }
                else
                {
                    ErrorHandle::Handle('Нарушение прав доступа к форме "'.$RequestedForm.'" класса "'.$ClassName.'".',3);
                    $AccessGranted = false;
                }
            }
            else
            {
                $AccessGranted = self::CheckFormPermissions($RequestedForm,$ClassName);
            }

            return $AccessGranted;
        }

        public static function CheckClassAccess($ClassName)
        {
            $AccessGranted = true;
            if (!class_exists($ClassName))
            {
                ErrorHandle::Handle('Попытка создания несуществующего класса '.$ClassName.'.',3);
                $AccessGranted = false;
            }
            elseif (!self::IsUserAuthorized())
            {
                if (!self::GetStaticProperty($ClassName,'UnAuthUse'))
                {
                    ErrorHandle::Handle('Нарушение прав доступа к объекту класса '.$ClassName.'.',3);
                    $AccessGranted = false;
                }
            }
            else
            {
                $AccessGranted = self::CheckClassPermissions($ClassName);
            }
            return $AccessGranted;
        }

        public static function ProcessMessage(&$Data)
        {
            $Result = false;

            if (!isset($Data['Object']))
            {
                ErrorHandle::ActionErrorHandle("Не указан класс объекта для обработки события.", 0);
            }
            elseif (!isset($Data['Action']))
            {
                ErrorHandle::ActionErrorHandle("Не задано событие для обработки объектом ".$Data['Object'].".", 0);
            }
            else
            {
                $ClassName = $Data['Object'];
                $Action = strtolower($Data['Action']);
                if (!class_exists($ClassName))
                {
                    ErrorHandle::Handle('Отсутствует класс '.$ClassName.' для обработки события '.$Action.'.', 3);					
                }
                elseif (!($ActionArray = self::GetStaticProperty($ClassName,'Actions') and !is_null($ActionArray[strtolower($Action)])))
                {
                    ErrorHandle::Handle('В классе '.$ClassName.' отсутствует модуль обработки события '.$Action,3);
                }
                else
                {
                    $MethodName = $ActionArray[$Action];

                    if (!method_exists($ClassName,$MethodName))
                    {
                        ErrorHandle::ActionErrorHandle('Метод '.$MethodName.' обработки события '.$Action.' не определен в объекте '.$ClassName.'.', 1);
                    }
                    elseif (!Controller::CheckActionAccess($Action,$ClassName))
                    {
                        ErrorHandle::ActionErrorHandle('Нарушение прав доступа к обработчику события '.$Action.' класса '.$ClassName.'.', 1);
                    }
                    else
                    {
                        $Object = call_user_func_array(array($ClassName,'GetObject'),array($Data,null));
                        if (!call_user_func(array($Object,$MethodName)))
                        {
                            ErrorHandle::ActionErrorHandle('Ошибка при вызове обработчика события '.$Action.' класса '.$ClassName.'.', 1);
                        }
                        else
                        {
                            $Result = true;
                        }
                    }
                }
            }
            unset($_GET);

            $_GET['Object'] = 'System';
            $_GET['Form'] = 'ErrorHandle';

            return $Result;
            // DONE 5 -o Molev  -c Output: Form base output Object - ErrorHandle Form - ActionResultForm
        }

        public static function CreateView(&$Data)
        {
            $Result = null;
            if (!isset($Data['Object']))
            {
                ErrorHandle::Handle("Не указан класс объекта для построения отображения.", 0);
            }
            elseif (!isset($Data['Form']))
            {
                ErrorHandle::Handle("Не задана форма построения отображения объекта ".$Data['Object'].".", 0);
            }
            else
            {
                $ClassName = $Data['Object'];
                $FormName = strtolower($Data['Form']);

                if (!class_exists($ClassName))
                {
                    ErrorHandle::Handle('Отсутствует класс '.$ClassName.' для построения отображения '.$FormName.'.', 1);
                }
                elseif (!Controller::CheckFormAccess($FormName,$ClassName))
                {
                    ErrorHandle::Handle('Нарушение прав доступа к форме '.$FormName.' класса '.$ClassName.'.', 1);
                }
                elseif (!($Forms = self::GetStaticProperty($ClassName,'Forms') and isset($Forms[$FormName])))
                {
                    ErrorHandle::Handle('В классе '.$ClassName.' отсутствует форма отображения '.$FormName,3);
                }
                else
                {
                    $View = new Form($Data,$ClassName,$FormName);
                    $Result = $View->CreateView();
                    unset($View);
                }
            }
            return $Result;
        }

        public static function IsUserAuthorized()
        {
            $system = System::GetObject();
            return $system->IsUserAuthorized();
        }

        static public function GetObject(&$ProcessData,$id=null)
        {
            return self::GetObjectInstance($ProcessData,null,__CLASS__);
        }

        public static function Run()
        {
            $Controller = Controller::GetObject($_POST);
            $System = System::GetObject();

            if (count($_POST) > 0) 
            {
                $result = Controller::ProcessMessage($_POST);
                $result = ErrorHandle::SystemStatusOutput();
            }
            elseif ((!isset($_GET['Ajax'])) or ($_GET['Ajax'] == 0))
            { 
                
                if (!isset($_GET['Object']) or !isset($_GET['Form'])) 
                {
                    global $DefaultForm;
                    $_GET = $DefaultForm;
                }
                
                $result = Controller::CreateView($_GET);

                global $AuthTemplate;
                global $UnAuthTemplate;

                $Temp_GET = $System->IsUserAuthorized(false)?$AuthTemplate:$UnAuthTemplate;
                $Template = Controller::CreateView($Temp_GET);


                if (!is_null($Template) and ($Template !=''))
                {
                    $result = str_replace('<!--#work_field#-->',$result,$Template);  
                    $result = str_replace('<!--#title#-->','<title>'.$System->Title.'</title>',$result);  
                }
                elseif (is_null($result) or ($result ==''))
                {
                    $result = ErrorHandle::SystemStatusOutput();
                }
            }
            else
            {
                $result = Controller::CreateView($_GET);
            }
                    

            return $result;
        }

    }         
?>
