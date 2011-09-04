<?php
    class Form
    {
        protected $ViewData;                                                                         // текущий GET запрос от окружения

        protected $Form;
        protected $ClassName;
        protected $Object;
        protected $ObjectID;

        protected $Body;                                                                    // Результат шаблонирования

        protected $Content = array('header'=>'','element'=>array(),'footer'=>'');          // Содержимое шаблона
        protected $RectCollection = array('header'=>array(),'element'=>array(),'footer'=>array());   // Коллекция прямоугольных областей в шаблоне

        public $Forms = array();

        /*  
        *   Загрузка содержимого файла шаблона для дальнейшей обработки
        *   
        *   Параметры
        *       $FileName   - <string>  - имя файла шаблона, который будет разбираться  
        *   Возврат 
        *                   - <bool>    - true - содержимое загружено, false - при ошибке загрузки.
        *   Изменяемые поля:
        *       FileName    - имя файла (если файл сеществует и доступен на чение) или пустая строка;
        *       Content     - содержимое файла (если файл сеществует и доступен на чение) или пустая строка;
        * 
        */
        protected function Load($FileName)
        {
            $Result = true;  
            if (!file_exists($FileName))
            {
                ErrorHandle::Handle("Oтсутсвует файл шаблона ".$FileName.".",2);
                $Result = false;
            } 
            elseif (!(is_readable($FileName)))
            {
                ErrorHandle::Handle("Нет доступа на чтение к файлу шаблона ".$FileName.".",2);
                $Result = false;
            }
            elseif (($Content = file_get_contents($FileName)) === false)
            {
                ErrorHandle::Handle("Ошибка загрузки файла шаблона ".$FileName.".",2);
                $Result = false;
            }
            else
            {
                // TODO 5 -o Molev  -c Output: Str -> split
                $StartPos = strpos($Content,'<!-- Element Begin -->');
                $FinishPos = strpos($Content,'<!-- Element End -->');

                if ($StartPos === false or $FinishPos === false or ($FinishPos <=$StartPos))
                {
                    $this->Content['header'] = $Content;
                    $this->Content['footer'] = '';
                    $this->Content['element'] = array();
                }
                else
                {
                    $this->Content['header'] = substr($Content,0,$StartPos); 
                    $this->Content['footer'] = substr($Content,$FinishPos+strlen('<!-- Element End -->')+1); 

                    $ElementStart = $StartPos+strlen('<!-- Element Begin -->')+1;
                    $ElementContent = substr($Content,$ElementStart,$FinishPos-$ElementStart-1); 
                    $LoopBound = $this->Object->count();
                    for ($i = 0;$i<$LoopBound;$i++)
                    {
                        $this->Content['element'][$i] = $ElementContent;
                    }
                }
            }
            return $Result;
        }

        protected function PrepareRect($SourceBlock,$Object)
        {
            $_RegExpression = '|\{\?(.+)\}|U';
            $_BlockIndex    = 0;
            $_RectIndex     = 1;

            $BlocksList         = array();
            $FieldThisObject    = array();
            $WhiteSpaces        = array(" ","\t","\n","\r","\0","\x0B");

            $BlockRecords=array();

            if (preg_match_all($_RegExpression, $SourceBlock, $BlocksList)) 
            {  
                foreach($BlocksList[$_BlockIndex] as $key=>$value)
                {
                    $BlockStr = str_replace($WhiteSpaces,"",$BlocksList[$_RectIndex][$key]);
                    parse_str($BlockStr,$ParamArr);
                    $Record = array();
                    $Record['Params']          = array();              // параметры метода

                    foreach ($ParamArr as $ParamKey => $ParamValue)
                    {
                        switch ($ParamKey)
                        {
                            case 'Var'      : $Record['FieldName']          = $this->EvalExpr($ParamValue,$Object); break;      // название получаемого поля объектов
                            case 'Object'   : $Record['ClassName']          = $ParamValue; break;        // название класса объектов
                            default         : $Record['Params'][$ParamKey]  = $this->EvalExpr($ParamValue,$Object);
                        }
                    }

                    if ($Record['ClassName'] == 'this')
                    {
                        unset($Record['Params']);
                        unset($Record['ClassName']);
                    }
                    $Record['Pattern']         	=$BlocksList[$_BlockIndex][$key];   // параметры метода
                    $BlockRecords[]             = $Record;
                }
            }
            return $BlockRecords;
        }

        //   Формирование коллекции прямоугольных областей и объектов, связанных с ними
        /*   
        *   Параметры
        *       <void>
        *   Возврат 
        *                   - <bool>    - true - коллекция проинциализирована, false - при возникновении ошибок.
        *   Изменяемые поля:
        *       RectCollection      - коллекция прямоугольных областей шаблона
        */
        protected function InitRects()
        {
            $this->RectCollection['header'] =  $this->PrepareRect($this->Content['header'],$this->Object);
            $this->RectCollection['footer'] =  $this->PrepareRect($this->Content['footer'],$this->Object);

            if (count($this->Content['element']) > 0)
            {
                $LoopBound = $this->Object->count();
                for ($i = 0;$i<$LoopBound;$i++)
                {
                    $this->RectCollection['element'][$i] = $this->PrepareRect($this->Content['element'][$i],$this->Object->get($i));
                }
            }

            return true;
        }

        protected function EvalExpr($Expr,$Object)
        {
            if (is_array($Expr))
            {
                foreach ($Expr as $key=>$Value)
                {
                    $Expr[$key] = $this->EvalExpr($Value,$Object);
                }
                return $Expr;

            }
            else
            { 
                if (($OpenBracketPos = stripos($Expr,"(",0)) === false)
                {
                    return $Expr;
                }
                elseif (($CloseBracketPos = stripos($Expr,")",$OpenBracketPos+1)) === false)
                {
                    return substr($Expr,0,$OpenBracketPos-1);
                }
                else
                {
                    $FuncName   = substr($Expr,0,$OpenBracketPos);
                    $ArgName    = substr($Expr,$OpenBracketPos+1,$CloseBracketPos-$OpenBracketPos-1);
                    switch (strtoupper($FuncName))
                    {
                        case "POST":    return isset($_POST[$ArgName])      ?$_POST[$ArgName]:''; 
                        case "GET":     return isset($_GET[$ArgName])       ?$_GET[$ArgName]:'';
                        case "COOKIE":  return isset($_COOKIE[$ArgName])    ?$_COOKIE[$ArgName]:'';
                        case "SESSION": return isset($_SESSION[$ArgName])   ?$_SESSION[$ArgName]:'';
                        case "INDEX":   return $this->Object->Index;
                        case "COUNT":   return $this->count();
                        case "THIS":    return $Object->$ArgName; 
                        case "PARAM":   return $this->ViewData[$ArgName];
                        case "SELECTED": 
                        {
                            return (isset($this->ViewData['CurrentID']) and $this->ViewData['CurrentID'] == $Object->ID)?"Selected":""; break;
                        }
                        case "CHECKED": 
                        {
                            return (isset($Object->$ArgName) and ($Object->$ArgName))?"Checked":""; break;
                        }
                        default :      
                        {
                            if (Controller::CheckClassAccess($FuncName))
                            {
                                $null = '';
                                $SubObject = call_user_func_array(array($FuncName,'GetObject'),array(&$null,&$null));
                                return  $SubObject->$ArgName; 
                            }
                            else
                            {
                                return $ArgName;
                            }
                        }
                    }
                }
            }
        }

        protected function GetRectResult(&$Record)	
        {
            if (isset($Record["ClassName"]) and (!is_null($Record["ClassName"])) and ($Record["ClassName"]!= ""))
            {
                $ClassName = &$Record["ClassName"];
                if (Controller::CheckClassAccess($ClassName))
                {
                    if (isset($Record['FieldName'])) 
                    {
                        $SubObject = call_user_func_array(array($ClassName,'GetObject'),array(&$Record['Params'],null));
                        $result = $SubObject->$Record['FieldName'];
                    }
                    else
                    {
                        $Record['Params']['Object'] = $ClassName;
                        $result = Controller::CreateView($Record['Params']);
                    }
                }
                else
                {
                    $result = '';
                }
            }
            else
            {
                $result = $Record["FieldName"];
            }
            return $result;
        }
        //   Заполнение коллекции прямоугольных областей конкретными результатми отображения объектов
        /*   
        *   Параметры
        *       <void>
        * 
        *   Возврат 
        *       <void>
        * 
        *   Изменяемые поля:
        *       RectCollection      - коллекция прямоугольных областей шаблона
        */
        protected function FillRectResults()
        {

            foreach($this->RectCollection['header'] as $key => $Record)
            {
                $this->RectCollection['header'][$key]["Result"] = $this->GetRectResult($Record);
            }

            foreach($this->RectCollection['footer'] as $key => $Record)
            {
                $this->RectCollection['footer'][$key]["Result"] = $this->GetRectResult($Record);
            }

            $LoopBound =count($this->RectCollection['element']);
            for ($i = 0;$i<$LoopBound;$i++)
            {
                foreach($this->RectCollection['element'][$i] as $key => $Record)
                {
                    $this->RectCollection['element'][$i][$key]["Result"] = $this->GetRectResult($Record);
                }
            }
        } 

        //   Заполнение тела шаблона результатми отображения объектов
        /*   
        *   Параметры
        *       <void>
        * 
        *   Возврат 
        *       <void>
        * 
        *   Изменяемые поля:
        *       Body
        */        
        protected function InsertRects()
        {
            $this->Body ='';

            $Cont = $this->Content['header'];

            foreach($this->RectCollection['header'] as $Rect)
            {
                $Cont = str_replace($Rect["Pattern"], $Rect["Result"], $Cont);
            }
            $this->Body .= $Cont;



            $LoopBound =count($this->Content['element']);
            for ($i = 0;$i<$LoopBound;$i++)
            {
                if (isset($this->Content['element'][$i]))
                {
                    $Cont = $this->Content['element'][$i];
                }
                else
                {   
                    $Cont = '';
                }

                foreach($this->RectCollection['element'][$i] as $Rect)
                {
                    $Cont = str_replace($Rect["Pattern"], $Rect["Result"], $Cont);
                }
                $this->Body .= $Cont;
                // TODO 5 -o Molev  -c Output: Проверить логику работы . М. б. выводится 1 элемент.
            }



            $Cont = $this->Content['footer'];

            foreach($this->RectCollection['footer'] as $Rect)
            {
                $Cont = str_replace($Rect["Pattern"], $Rect["Result"], $Cont);
            }
            $this->Body .= $Cont;
        }

        /* 
        *   Создает отображение шаблона
        *   Параметры
        *       FileName - <string>     - Имя файла шаблона
        *         
        * 
        *   Возврат 
        *       <string>    - результат заполнения шаблона
        *       
        *   Изменяемые поля:
        *       Body                - Результат заполнения шаблона
        *       RectCollection      - коллекция прямоугольных областей шаблона
        */     
        public function GetView($FileName)
        {
            if ($this->Load($FileName)) 
            {
                $this->InitRects();
                $this->FillRectResults();
                $this->InsertRects();
            }
            else
            {
                $this->Body = '';
            }
            return $this->Body;
        }

        public function CreateView()
        {             
            $ClassName = $this->ClassName;
            if ((!is_null($this->Form)) and ($Forms = Singleton::GetStaticProperty($ClassName,'Forms')) and (isset($Forms[$this->Form])))
            {
                $FileName = $Forms[$this->Form];
                return $this->GetView($FileName);
            }
            else
            {
                return '';
            }
        }

        public function __construct(&$ViewData,$ClassName,$RequestedForm)  
        {   
            $null = null;
            $this->ViewData = $ViewData;
            $this->ClassName = $ClassName;
            $this->Form = $RequestedForm;


            if (isset($this->ViewData['ID']) and is_numeric($this->ViewData['ID'])) $this->ObjectID = $this->ViewData['ID'];

            if (class_exists($ClassName))
            {
                if (is_subclass_of($ClassName, 'CollectionDB') and isset($this->ViewData['Filter']) and is_array($this->ViewData['Filter']))
                {
                    $PD['Filter'] = $this->ViewData['Filter'];
                    $this->Object = call_user_func_array(array($ClassName,'GetObject'), array(&$PD,$this->ObjectID));
                }
                elseif ($ClassName == 'WorkBlockList')
                {
                    $this->Object = call_user_func_array(array($ClassName,'GetObject'), array(&$this->ViewData,$this->ObjectID));
                }
                else
                {
                    $this->Object = call_user_func_array(array($ClassName,'GetObject'), array(&$this->ViewData,$this->ObjectID));
                }
            }
            else
            {

            }
        }    

    }
?>
