<?php header('Content-type: text/html; charset=utf-8');
//error_reporting(E_ALL);

define("WhereParamPrefix", "_");

Main::route();

class ConfigDB {

	const host		= "127.0.0.1";
	const dbname	= "my_db_name";
	const user		= "root";
	const pass		= "";
	
}

class Config {

	const defPageSize = 10;
	
}

class Main {
	
	static function route() {
		
		if (array_key_exists(ParamNames::Clas, $_REQUEST)) {

			$className = $_REQUEST[ParamNames::Clas];
			if (class_exists($className)) {
		  		$clas = new $className();
			}
			else {
				(new FormError("Не обнаружен класс ".$className))->answer();
				exit;
			}
			
	    	if (array_key_exists(ParamNames::Submit, $_REQUEST)) {
	    		$methodName = "action".$_REQUEST[ParamNames::Submit];
				if (method_exists($clas, $methodName)) {
					$clas->$methodName();	// выполнение action
					HtmlPage::echoBody(__CLASS__, $clas->render());	// после action выводим только body или сообщение об ошибке (action отправляется только черех XMLHttpRequest)
					exit;
				}
				else {
					$clas->strError = "В классе ".get_class($this)." нет метода ".$methodName;
				}
			}

			// ВЫВОД
			HtmlPage::echoPage(__CLASS__, $clas->render());	// экшэна не было, выводим страницу
			
		}
		
		else {
			FrmAllTables::answer();	// класс по умолчанию, главная страница
		}
		
		Html2::final();
		
	}
	
}

class FrmAllTables {
	
	public static function answer() {
    	
    	$frmBuffer	= "";
    	
    	$attrs = array("class"=>get_called_class());
		$frmBuffer	.= Html2::open(__CLASS__, "div", $attrs);

    	$pdoStmt = DbHelper::getTables();
    	
		while ($row = $pdoStmt->fetch())	{
			$name = $row["table_name"];
			$params = array(	ParamNames::Clas	=> "FrmTable",
								ParamNames::Table	=> $name
							);
			
			$frmBuffer .= Html::PA($name, "?", $params);
		}
		
		$frmBuffer	.= Html2::close(__CLASS__, "div");
		
		HtmlPage::echoPage(__CLASS__, $frmBuffer);

    }
	
}

class RecordActions {
    const Add		= "Add";
    const Delete	= "Delete";
    const Edit		= "Edit";
}

class ParamNames {
    const Action	= "taed_a";
    const Clas		= "taed_c";
    const Page		= "taed_p";
    const Submit	= "taed_s";
    const Table		= "taedd_t";
    const Key		= "taed_k";
}

class PreparedQuery {
	public $query;	// текст запроса
	public $params;	// массив со значениями параметров
}


abstract class FrmAbstract {
	
	protected $strReferer	= ""; 		// Ссылка, куда можно вернуться
	protected $strSuccess	= "";		// Текст подтверждения о сохранении данных
	protected $strError		= "";		// Одна большая ошибка
	
	// Отрисовка формы.
	// $again - повторная отрисовка после неудачи в обработчике
    abstract public function render();

    protected function errorRender() {
		echo $this->strError;
	}
    
}


class FormError extends FrmAbstract {
	
	function __construct($error) {
		$this->strError = $error;
	}
	
    public function render() {
    	
		$frmBuffer	= "";
    	
    	if (!empty($this->strReferer)) {
    		$frmBuffer .= Html::PA("Назад", $this->strReferer);
		}
		
    	if (!empty($this->strError)) {
    		$frmBuffer .=  Html::P($this->strError);
		}
/*		
    	if (!empty($this->strSuccess)) {
    		$frmBuffer .=  Html::P($this->strSuccess);
		}
*/		
			
		HtmlPage::echoPage(__CLASS__, $frmBuffer);

    }
    
}

class FrmTable extends FrmAbstract
{
	protected $tableName;
	protected $keysArray;	// необходимые ключи
	protected $paramKeys;	// полученные ключи

	function __construct() {
		
		//$tableName проверить есть ли в базе такая таблица
		$tmpTableName = $_REQUEST[ParamNames::Table];
		if (DbHelper::tableExists($tmpTableName)) {
			$this->tableName = $tmpTableName;
		}
		else {
			(new FrmAbstract("Не обнаружена таблица ".$tmpTableName))->answer();
			exit;
		}
		
		$this->keysArray = DbHelper::getKeys($this->tableName);
		if (array_key_exists(ParamNames::Key, $_REQUEST)) {
			$this->paramKeys = json_decode(stripslashes($_REQUEST[ParamNames::Key]), TRUE);
		}
		else {
			$this->paramKeys = array();
		} 
		
	}


	protected function renderForm($action) {
		
		$idDivForm = "";
		$idForm = "";
		switch ($action) {
		    case RecordActions::Add:
		        //$clas = " class='FrmAdd'";
		        $idDivForm = "idDivAddForm";
		        $idForm = "idAddForm";
		        $idFormMessage = "idAddFormMessage";
		        $btnText = "Добавить";
		        break;
		    case RecordActions::Delete:
		        $idDivForm = "idDivDeleteForm";
		        $idForm = "idDeleteForm";
		        $idFormMessage = "idDeleteFormMessage";
   		        $btnText = "Удалить";
		        break;
		    case RecordActions::Edit:
		        //$clas = " class='FrmEdit'";
		        $idDivForm = "idDivEditForm";
		        $idForm = "idEditForm";
		        $idFormMessage = "idEditFormMessage";
		        $btnText = "Изменить";
		        break;
		}
		
    	$pdoStmt = DbHelper::getColumns($this->tableName);
    	
		$frmBuffer = "";
		$frmBuffer .= "<div id='".$idDivForm."' class='RecordFrm'><form id='".$idForm."' method='post' class='RecordFrm'>\n";
		$frmBuffer .= "<table><tr><th>Имя</th><th width='100%'>Значение".Html::WindowCloseButton()."</th></tr>\n";
		
		while ($row = $pdoStmt->fetch(PDO::FETCH_ASSOC))	{

			$name = $row["column_name"];
			
			if ($row["column_name"]=='id') {
				if ($action == RecordActions::Add) {
					continue;
				}
			}
			
			$value = " value='".$row["COLUMN_DEFAULT"]."'";
			$disabled="";
			if ($action == RecordActions::Delete) {
				$disabled=" disabled";
			}
			$input = "<input type='text' name=$name$value$disabled>";
			
			$frmBuffer .=  "<tr>\n";
			$frmBuffer .=  "<td>".$row["column_name"]."</td>\n";
			$frmBuffer .=  "<td>$input</td>\n";
			$frmBuffer .=  "</tr>\n";
		}

		$frmBuffer .=  "<tr class='SubmitTR'><td><button type='submit' name='".ParamNames::Submit."' value='".$action.
						"' onclick='sendForm(`".$idForm."`); return false;'>".$btnText."</button></td><td><div id='".$idFormMessage.
						"'></div></td></tr></table>\n";
		
		$input = "<input type='text' disabled hidden name=".ParamNames::Key.">";
		$frmBuffer .=  $input;

		$frmBuffer .=  "</form></div>\n";

		return $frmBuffer;
	}
	
    protected function renderMessageForm() {
       	$frmBuffer	= "";
    	if (!empty($this->strSuccess)) {
    		$frmBuffer .= "<div class='RecordFrm visible'><div class='MessageWindow MessageWindowSuccess'>"
    						.Html::WindowCloseButton().$this->strSuccess."</div></div>";
		}
    	if (!empty($this->strError)) {
    		$frmBuffer .= "<div class='RecordFrm visible'><div class='MessageWindow MessageWindowError'>"
    						.Html::WindowCloseButton().$this->strError."</div></div>";
		}
		return $frmBuffer;
	}

    private function createUpdateQuery() {

		$preparedQuery = New PreparedQuery;
		$query	= &$preparedQuery->query;
		$params	= &$preparedQuery->params;
		
		$query	= "";
		$params	= array();
		
		$query .= "UPDATE `".$this->tableName."` SET ";
		
    	$pdoStmt = DbHelper::getColumns($this->tableName);
    	$first = TRUE;
		while ($row = $pdoStmt->fetch())	{
			
			$name = $row["column_name"];
			$paramName = str_replace('-', '_', $name);		// вылазит ошибка если есть дефис в имени параметра
			
			if (!$first) {
				$query .= ",\n";
			}
			$query .= "`".$name."`=:".$paramName;
			
			if (array_key_exists($name, $_REQUEST)) {
				$params[$paramName] = $_REQUEST[$name];
			}
			
			$first = FALSE;
		}
		
		$whereArr = array();
		foreach($this->paramKeys as $key => $value) {
			$paramName = constant("WhereParamPrefix").$key;
			array_push($whereArr, "`".$key."`=:".$paramName);
			$params[$paramName] = $value;
		}
		
		$query .= " WHERE ".implode(" AND ", $whereArr)." LIMIT 1";
		
		return $preparedQuery;
		
	}
    
    private function createDeleteQuery() {

		$preparedQuery = New PreparedQuery;
		$query	= &$preparedQuery->query;
		$params	= &$preparedQuery->params;
		
		$query	= "";
		$params	= array();
		
		$query .= "DELETE FROM `".$this->tableName."`";
		
		$whereArr = array();
		foreach($this->paramKeys as $key => $value) {
			$paramName = constant("WhereParamPrefix").$key;
			array_push($whereArr, "`".$key."`=:".$paramName);
			$params[$paramName] = $value;
		}
		
		$query .= " WHERE ".implode(" AND ", $whereArr)." LIMIT 1";
		
		return $preparedQuery;
		
	}
    
    private function createInsertQuery() {

		$preparedQuery = New PreparedQuery;
		$query	= &$preparedQuery->query;
		$params	= &$preparedQuery->params;
		
		$query	= "";
		$params	= array();
		
		$query .= "INSERT INTO `".$this->tableName."` ";
		
    	$pdoStmt = DbHelper::getColumns($this->tableName);
    	
    	$strParams = "";
    	$strValues = "";
    	
    	$first = TRUE;
		while ($row = $pdoStmt->fetch())	{
			
			$name = $row["column_name"];
			
			if ($name == 'id') {
				continue;
			}
			
			$paramName = str_replace('-', '_', $name);		// вылазит ошибка если есть дефис в имени параметра
			
			if (!$first) {
				$strParams .= ",\n";
				$strValues .= ",\n";
			}
			$strParams .= "`".$name."`";
			$strValues .= ":".$paramName;
			$first = FALSE;
			
			$value = "";
			if (array_key_exists($name, $_REQUEST)) {
				$value = $_REQUEST[$name];
				$params[$paramName] = $_REQUEST[$name];
			}

		}

		$query .= "(".$strParams.") VALUES (".$strValues.")";
		
		return $preparedQuery;
		
	}

    public function actionAdd() {

		$preparedQuery	= $this->createInsertQuery();
    	
		$pdo = DbHelper::pdo_connect();
		$stmt = $pdo->prepare($preparedQuery->query);
		
		try {
			$result = $stmt->execute($preparedQuery->params);
			$this->strSuccess = "Запись добавлена!";
		} catch (Exception $e) {
			$this->strError = "Не удалось встаить запись:<br>";
			$this->strError .= $stmt->errorInfo()[2];
		}		
		
	}
	
	private function testKeysPresents() {
		
		$testArr = array_diff_key($this->paramKeys, $_REQUEST);
		if (count($testArr) > 0) {
			$this->strError = "Не все ключи указаны:<br>";
			$this->strError .= implode(", ", $testArr);
			echo $this->strError;
			exit;
		}
	}
	
    public function actionEdit() {
    	
    	$this->testKeysPresents();

		$preparedQuery	= $this->createUpdateQuery();
    	
		$pdo = DbHelper::pdo_connect();
		$stmt = $pdo->prepare($preparedQuery->query);
		
		try {
			$result = $stmt->execute($preparedQuery->params);
			$this->strSuccess = "Запись изменена!";
		} catch (Exception $e) {
			$this->strError = "Не удалось изменить запись:<br>";
			$this->strError .= $stmt->errorInfo()[2];
			echo $this->strError;
			exit;
		}		
	}
	
    public function actionDelete() {

    	$this->testKeysPresents();

		$preparedQuery	= $this->createDeleteQuery();
    	
		$pdo = DbHelper::pdo_connect();
		$stmt = $pdo->prepare($preparedQuery->query);
		
		try {
			$result = $stmt->execute($preparedQuery->params);
			$this->strSuccess = "Запись удалена!";
		} catch (Exception $e) {
			$this->strError = "Не удалось удалить запись:<br>";
			$this->strError .= $stmt->errorInfo()[2];
			echo $this->strError;
			exit;
		}		
		
	}
	
    public function render() {
    
    	$jsArrKeys = array_fill_keys($this->keysArray, '');
    	
       	$frmBuffer	= "";
       	$frmBuffer .= "<div id='jsObjKeys' style='display: none;' data-keys='".htmlspecialchars(json_encode($jsArrKeys))."'></div>";
       	$frmBuffer .= $this->renderMessageForm();
    	
    	// Вычисляем страницы
    	
		$pdo = DbHelper::pdo_connect();
		$queryText = "SELECT count(*) FROM `".$this->tableName."`";
		$stmt = $pdo->query($queryText);
		$count = 0;
		if ($stmt !== FALSE) {
			$count = $stmt->fetchColumn();
		}
		else {
			// todo error
		}
		
		$maxPage = intdiv($count, Config::defPageSize);
		if ($maxPage*Config::defPageSize < $count) {
			$maxPage += 1;
		}
		if ($maxPage == 0) {
			$maxPage = 1;
		}

		$page = 1;
    	if (array_key_exists(ParamNames::Page, $_REQUEST)) {
    		$page = max( (int)$_REQUEST[ParamNames::Page], 1);
		}
		if ($page > $maxPage) {
			$page = $maxPage;
		}
		$offset = ($page-1) * Config::defPageSize;
		$row_count = Config::defPageSize;
    	
    	// Рисуем
    	
    	// НАЧАЛО СТРАНИЦЫ И ТАБЛИЦЫ
    	
    	$attrs = array("class"=>get_called_class());
		$frmBuffer	.= Html2::open(__CLASS__, "div", $attrs);

		$frmBuffer .= $this->renderPager($page, $maxPage);
		$frmBuffer	.= Html2::open(__CLASS__, "div", array("class"=>"div100"));
		$frmBuffer .= Html2::open(__CLASS__, "table");

		// ЗАГОЛОВКИ ТАБЛИЦЫ
		
		$frmBuffer .= Html2::open(__CLASS__, "tr");
		
		$frmBuffer .= Html::TH(Html::UtfAdd, "2", "UtfSymbols");
		
    	$pdoStmt = DbHelper::getColumns($this->tableName);
		while ($row = $pdoStmt->fetch(PDO::FETCH_ASSOC))	{
			$frmBuffer .=  Html::TH($row["column_name"]);
		}
		
		
		$frmBuffer .= Html2::close(__CLASS__, "tr");

		// СТРОКИ ТАБЛИЦЫ

		$pdo = DbHelper::pdo_connect();
		$stmt = $pdo->prepare("SELECT * FROM `".$this->tableName."` LIMIT :offset, :row_count");
		$stmt->execute(array(':offset' => $offset, ':row_count' => $row_count));
		
		$recordId = 0;
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			
			$recordId++;


			$aClass = "";
			
			// Открываем строку таблицы
			$attrsKey = array(	"data-key"	=> $recordId
							);
			$frmBuffer .= Html2::open(__CLASS__, "tr", $attrsKey);
			

			$a = Html::UtfEdit($aClass, $attrsKey);
			$frmBuffer .=  Html::TD($a, "", $clas="UtfSymbols");

			$aClass = "UtfSymbols SymbolDelete";
			$a = Html::UtfDelete($aClass, $attrsKey);
			$frmBuffer .=  Html::TD($a, "", $clas="UtfSymbols");
			
			// Поля таблицы
			foreach ($row as $key => $value) {
				$attrs = array(	"data-field"	=> $key
							);
				$frmBuffer .=  Html::TD($value, "", "", $attrs);
			}
			
			// Закрываем строку таблицы
			$frmBuffer .= Html2::close(__CLASS__, "tr");
			
		}
		
		// КОНЕЦ ТАБЛИЦЫ И СТРАНИЦЫ
		
		$frmBuffer .= Html2::close(__CLASS__, "table");

		$frmBuffer .= $this->renderForm(RecordActions::Add);
		$frmBuffer .= $this->renderForm(RecordActions::Edit);
		$frmBuffer .= $this->renderForm(RecordActions::Delete);

		
		$frmBuffer	.= Html2::close(__CLASS__, "div");	// div100


		$frmBuffer	.= Html2::close(__CLASS__, "div");	// FrmTable


		return $frmBuffer;


    }
    
	private function renderLink($page, $text="", $clas="") {
		
		$strClass = get_class($this);
		
		if($text=="") {
			$text = $page;
		}
		
		$params = array(	
						ParamNames::Clas	=> $strClass,
						ParamNames::Table	=> $this->tableName,
						ParamNames::Page	=> $page
						);
		return Html::A("&nbsp;".htmlentities($text)."&nbsp;", "?", $params, $clas);
			
	}
	
	private function getPageArray($page, $maxPage) {
		
		$arr = array(1,$maxPage);
		
		$exp = strlen("".PHP_INT_MAX)-1;
		for ($step=(int)pow(10, $exp); $step>=1; $step = $step/10) {
			
			if ($step>$maxPage) {
				continue;
			}
			
			$pageRound = floor($page/$step)*$step;
			for ($i=$pageRound-15*$step; $i<=$pageRound+15*$step; $i+=$step) {
				if ($i>=1 && $i<=$maxPage) {
					$arr[] = $i;
				}
			}
		}
		
		$arr = array_unique($arr);
		sort($arr);

		return $arr;
	}
	
	private function renderPagerSelect ($arrLinks, $page) {
		
		$frmBuffer	= "";
		
		$attrs = array("onchange" => "window.location.href=this.options[this.selectedIndex].value");
		$frmBuffer	.= Html2::open(__CLASS__, "select", $attrs);
		
		foreach($arrLinks as $key=>$value) {
			$selected = FALSE;
			if ($key == $page) {
				$selected = TRUE;
			}
    		$frmBuffer	.= Html::Option($key, $value, $selected);
		}

		$frmBuffer	.= Html2::close(__CLASS__, "select");

		return $frmBuffer;
		
	}
	

	private function renderPager($page, $maxPage) {
		$frmBuffer	= "";
		$frmBuffer	.= "<div class='Pager'>";
		
		// 1 и влево
		
		$clas = "";
		if ($page == 1) {
			$clas = "Disabled";
		}
		$frmBuffer	.= $this->renderLink(1, "", $clas);
		$frmBuffer	.= " ";
		$frmBuffer	.= $this->renderLink($page-1, "<", $clas);

		
		// средний селект
		$pageArray = $this->getPageArray($page, $maxPage);
		
		$arrLinks = array();
		foreach ($pageArray as $i) {
			$params = array(ParamNames::Clas		=> "FrmTable",
							ParamNames::Table		=> $this->tableName,
							ParamNames::Page	=> $i
							);
			$arrLinks[$i] = "?".http_build_query($params);
		}
		
		$frmBuffer	.= " ".$this->renderPagerSelect ($arrLinks, $page)." ";
		

		// вправо и макс
		$clas = "";
		if ($page == $maxPage) {
			$clas = "Disabled";
		}

		$frmBuffer	.= $this->renderLink($page+1, ">", $clas);
		$frmBuffer	.= " ";
		$frmBuffer	.= $this->renderLink($maxPage, "", $clas);

		
		$frmBuffer	.= "</div>";
		return $frmBuffer;
	}
	
	
}

class DbHelper
{
	
	static function pdo_connect() {

	    return new PDO(	"mysql:host=".ConfigDB::host.";dbname=".ConfigDB::dbname,
	    				ConfigDB::user,
	    				ConfigDB::pass,
	    				array(	PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode = ''",
	    						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	    						PDO::ATTR_EMULATE_PREPARES => FALSE	));
	}

	static function tableExists($tableName) {

		$dbName = self::getDBName();
		
		if ($dbName === FALSE) {
		 	return FALSE;
		}
		
		$pdo = self::pdo_connect();

		$query="
			SELECT 
			    count(*) AS cnt
			FROM 
			    information_schema.tables
			WHERE 
				table_schema = '$dbName'
				AND
			    table_name = :table_name
			";

		$stmt = $pdo->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmt->execute(array(':table_name' => $tableName));
		if ($row = $stmt->fetch()) {
			if ($row['cnt'] == 1) {
			    return TRUE;
			}
		}

		return FALSE;

	}

	static function getDBName() {
		
		$pdo = self::pdo_connect();

		$stmt = $pdo->query("SELECT database() AS the_db");
		if ($row = $stmt->fetch())	{
		    return $row["the_db"];
		}
		else {
		 	return FALSE;
		}

	}

	static function getKeys($tableName) {

		$dbName = self::getDBName();
		
		if ($dbName === FALSE) {
		 	return FALSE;
		}
		
		$pdo = self::pdo_connect();

		$query="
			SELECT
				GROUP_CONCAT(COLUMN_NAME SEPARATOR ',') indexes,
				index_name,
				CASE
					WHEN index_name = 'PRIMARY' THEN 1
					ELSE 0
				END prim,
				COUNT(1) index_count
			FROM
				INFORMATION_SCHEMA.STATISTICS
			WHERE
				TABLE_SCHEMA = '".$dbName."'
				AND TABLE_NAME = '".$tableName."'
			GROUP BY
				index_name
			ORDER BY
				prim DESC, COUNT(1)
			LIMIT 1		
			";

		$stmt = $pdo->query($query);
		
		$arr = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		if (count($arr) > 0) {
			$arr = explode(",", $arr[0]);
		}
		else {
			$stmt = self::getColumns($tableName);
			$arr = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		}

		return $arr;

	}

	static function getTables() {

		$dbName = self::getDBName();
		
		if ($dbName === FALSE) {
		 	return FALSE;
		}
		
		$pdo = self::pdo_connect();

		$query="
			SELECT 
			    table_name
			FROM 
			    information_schema.tables
			WHERE 
				table_schema = '$dbName'
			ORDER BY table_name
			";

		$stmt = $pdo->query($query);

		return $stmt;

	}

	static function getColumns($tableName) {

		$dbName = self::getDBName();
		
		if ($dbName === FALSE) {
		 	return FALSE;
		}
		
		$pdo = self::pdo_connect();
		
		// column_name долженн стоять первым для функции getKeys()
		$query="
			SELECT 
			    column_name, ordinal_position, data_type, column_type, column_comment, COLUMN_DEFAULT
			FROM 
			    information_schema.columns
			WHERE 
				table_schema = '$dbName'
				AND
			    table_name = '$tableName'
			ORDER BY ordinal_position
			";

		$stmt = $pdo->query($query);
		/* Пробовал добиться повторного обхода, но, похоже, с MySQL такой фокус все равно не проходит
		$stmt = $pdo->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
		$stmt->execute();
		*/
		return $stmt;

	}

	static function columnExists($tableName, $colName) {

		$dbName = self::getDBName();
		
		if ($dbName === FALSE) {
		 	return FALSE;
		}
		
		$pdo = self::pdo_connect();

		$query="
			SELECT 
			    count(*)
			FROM 
			    information_schema.columns
			WHERE 
				table_schema = '$dbName'
				AND
			    table_name = '$tableName'
			    AND
			    column_name = '$colName'
			";
		$stmt = $pdo->query($query);
		$count = 0;
		if ($stmt !== FALSE) {
			$count = $stmt->fetchColumn();
		}
		else {
			// todo error
		}
		
		if ($count==0) {
			return FALSE;
		}
		else {
			return TRUE;
		}

	}

	static function insert($tableName, $paramArray) {
		
		$columns = self::getColumns($tableName);
		
		if ($columns === FALSE) {
		 	return FALSE;
		}

		$fieldNames = "";
		$paramNames = "";
		while ($row = $columns->fetch(PDO::FETCH_ASSOC)) {
			if ($row['ordinal_position'] <> 1) {
			    $fieldNames .= ", ";
			    $paramNames .= ", ";
			}
		    $fieldNames .= $row['column_name'];
		    $paramNames .= ":".$row['column_name'];
		}

		$queryText = "INSERT INTO `$tableName` (".$fieldNames.") VALUES (".$paramNames.")";
		$pdo = pdo_connect();
		$stmt = $pdo->prepare($queryText);
		
		return $stmt->execute($paramArray);
	}


}

class Html {
	
    const UtfAdd		= "<a href='javascript:;' class='UtfSymbols SymbolAdd' onclick='document.querySelector(\"#idDivAddForm\").classList.add(\"visible\")'>&nbsp;+&nbsp;</a>";
    
	public static function UtfEdit($clas="", $argAttrs=array()) {
		$attrs = $argAttrs;
		$attrs["onclick"] = 'openEditWindow(this)';
		return self::A("&nbsp;&#9998;&nbsp;", "javascript:;", array(), $clas, $attrs);
	}
    
	public static function UtfDelete($clas="", $argAttrs=array()) {
		$attrs = $argAttrs;
		$attrs["onclick"] = 'openDeleteWindow(this)';
		return self::A("&nbsp;&#215;&nbsp;", "javascript:;", array(), $clas, $attrs);
	}
    
	public static function A($text = "", $path = "", $params = array(), $clas="", $attrs=array()) {
		$attrClass = "";
		if ($clas !== "") {
			$attrClass = " class='".$clas."'";
		}
		return "<a href='".$path.http_build_query($params)."'".$attrClass.Html2::getAttrs($attrs).">".$text."</a>";
	}
	
	public static function P($text = "") {
		return "<p>".$text."</p>";
	}
	
	public static function PA($text = "", $path = "", $params = array()) {
		return self::P(self::A($text, $path, $params));
	}

	public static function TH($text = "", $colspan = "", $clas = "") {
		return self::thtd("th", $text, $colspan, $clas);
	}
	
	public static function TD($text = "", $colspan = "", $clas = "", $attrs=array()) {
		return self::thtd("td", $text, $colspan, $clas, $attrs);
	}
	
	private static function thtd($tag, $text = "", $colspan = "", $clas = "", $attrs=array()) {
		$attrColspan = "";
		if ($colspan !== "") {
			$attrColspan = " colspan='".$colspan."'";
		}
		$attrClass = "";
		if ($clas !== "") {
			$attrClass = " class='".$clas."'";
		}
		return "<".$tag.$attrColspan.$attrClass.$attrClass.Html2::getAttrs($attrs).">".$text."</".$tag.">";
	}



	public static function Option($text = "", $value = "", $selected=FALSE) {
		$strSelected = "";
		if ($selected) {
			$strSelected = " selected";
		}
		return "<option value='".$value."'".$strSelected.">".$text."</option>";
	}
	
	
    public static function WindowCloseButton() {
    	return "<div class='Cls'><a href='javascript:;' class='X' onclick='this.parentElement.closest(\"div.RecordFrm\").classList.remove(\"visible\")'>&nbsp;X&nbsp;</a></div>";
	}
	

}


class Html2 {

	public static $arrTags = array();
	public static $arrClasses = array();
	

	private static function getAttrsCallback(&$value, $key, $quote="'") {
		$value = " ".$key."=".$quote.$value.$quote;
	}

	public static function getAttrs($attrs, $quote="'") {
		array_walk($attrs, 'self::getAttrsCallback', $quote);
		return implode(" ", $attrs);
	}

	public static function open($className, $tag, $attrs=array()) {
		array_push (self::$arrTags, $tag);
		array_push (self::$arrClasses, $className);
		return "<".$tag.self::getAttrs($attrs).">";
	}

	public static function close($className, $tag) {
		$lastTag = array_pop(self::$arrTags);
		$lastClass = array_pop(self::$arrClasses);
		if ($tag !== $lastTag || $className !== $lastClass ) {
			throw new Exception("Ошибка загрытия тега '".$lastTag."' класса ".$lastClass." тегом '/".$tag."' класса ".$className."\n");
		}
		return "</".$tag.">";
	}

	public static function final() {
		
		if (count(self::$arrTags) !== 0) {
			throw new Exception('Не закрыты теги: '.implode(", ", self::$arrTags)." классов ".implode(", ", self::$arrClasses) );
		}

	}
	
}


Class HtmlPage {
	
	static function echoPage($clas, $content="") {
		
		echo "<!DOCTYPE HTML>\n";
		echo "<html lang='ru'>\n";
		self::echoHead("TaEd");
		self::echoBody($clas, $content);
		echo "</html>";
	}

	public static function echoBody($clas, $content="") {
		echo "<body class='".$clas."'>";
		echo $content;
		echo "</body>";
	}

	private static function echoHead($title="", $description="", $keywords="", $prefetch="") {
		
		?>

		<head>
		<meta charset='UTF-8'>
		<title><?=$title?></title>
		<meta name='description' content='<?=$description?>'/>
		<meta name='keywords' content='<?=$keywords?>'/>
		
		<meta name=viewport content='width=device-width, initial-scale=1'>

		<link rel='shortcut icon' href='/favicon.png'>
		<link rel='apple-touch-icon' sizes='180x180' href='/apple-touch-icon.png'>
		<link rel='icon' type='image/png' sizes='32x32' href='/favicon-32x32.png'>
		<link rel='icon' type='image/png' sizes='16x16' href='/favicon-16x16.png'>
		<link rel='manifest' href='/manifest.json'>
		
		<meta name='theme-color' content='#ffffff'>
		<link rel='stylesheet' type='text/css' href='/style.css'/>
		<?=$prefetch?>
		
		<style>
			html, body {
				height: 100%;
				width: 100%;
			}
			html {
				display: table;
				margin: auto;
				max-width: 100%;
				font: 12pt arial;
			}
			body {
				display: table-cell;
				text-align: center;
				max-width: 100%;
			}
			body.FrmAllTables {
				vertical-align: middle;
			}
			body>div {
				box-sizing: border-box;
				display: inline-block;
				margin: auto;
				text-align: left;
			}
			body>div.FrmAllTables>p {
				margin-top: 0;
			}
			
			div.Pager {
				position: sticky;
				top: 0px;
				display: inline-block;
				margin: auto;
			}
			
			div.FrmTable {
				display: flex;
				flex-direction: column;
				height: 100vh;				
				box-sizing: border-box;
				max-width: 100vw;
				text-align: center;
				overflow-x: visible;
			}
			
			div.div100 {
				flex-grow: 3;
				box-sizing: border-box;
				max-width: 100%;
				text-align: center;
				overflow-x: auto;
			}
			
			.FrmTable th {
				background: LightGreen;
				position: sticky;
				top: 0px;
			}
			
			.FrmTable table {
				margin: auto;
				border-collapse: collapse;
				border-spacing: 0px;
			}
			th {
				text-align: center;
				padding: 0px 4px 0px 4px;
			}
			td {
				border: 2px solid LightGrey;
				padding: 0px 8px 0px 8px;
			}
			select {
				font-family: inherit;
				font-size: inherit;
			}
			.Pager {
				word-spacing: 10px;
				font-size: 1.1rem;
				text-align: center;
				padding: 10px;
			}
			.Pager a {
				background: LightGrey;
				text-decoration: none;
			}
			.Pager select {
				border: none;
				background-color: LightGrey;
				text-align: center;
				text-align-last: center;
				min-width: 200px;
			}
			.Pager option {
				background-color: white;
				font-size: 1rem;
			}
			.UtfSymbols {
				font-size: larger;
			}
			.SymbolEdit {
				color: Blue;
			}
			.SymbolDelete {
				color: Red;
			}
			.SymbolAdd {
				color: white;
				background-color: Green;
			}
			
			.UtfSymbols.SymbolAdd {
				font-size: medium;
			}
			
			td.UtfSymbols {
				padding: 0;
			}
			th.UtfSymbols {
				padding: 0;
			}
			td.UtfSymbols a {
				text-decoration: none;
			}
			th.UtfSymbols a {
				text-decoration: none;
			}
			a.Disabled {
				pointer-events: none;
				cursor: default;
				opacity: 0.4;
			}
			
			a.X {
				text-decoration: none;
			}
			
			div.RecordFrm.visible {
				display: block;
			}
			
			div.RecordFrm {
				display: none;
				background-color: rgba(255, 255, 255, 1);
				position: absolute;
				z-index: 999;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				vertical-align: middle;

			}
			div.MessageWindow  {
				margin: auto;
				padding: 10px;
				max-width: 400px;
				border: 2px solid;
				border-radius: 8px;
			}
			
			div.MessageWindowSuccess {
				margin: 50px auto auto auto;
				border-color: LightGreen;
			}
			div.MessageWindowError {
				border-color: Red;
			}
			
			form.RecordFrm {
				background-color: White;
				display: inline-block;
				margin: 5rem auto auto auto;
				width: 100%;
				max-width: 1000px;
				text-align: left;
			}			
			form.RecordFrm table,input{
				width: 100%;
				border: none;
			}			
			form.RecordFrm table,input:disabled{
				background-color: White;
			}			
			
			
			
			form.RecordFrm td {
				padding: 4px 8px 4px 8px;
			}
			
			
			.SubmitTR td {
				vertical-align:top;
			}
			button[type="submit"] {
				margin-top: 20px;
				border: 2px solid LightBlue;
				border-radius: 8px;
				padding: 6px 12px;
			}
			button[type="submit"]:hover {
				cursor: pointer;
			}

			
			.Cls {
				float: right;
			}
			.SubmitTR td {
				border: none;
			}

			@media (min-width:600px) {
				body>div.FrmAllTables {
				    -moz-column-count	: 2;
				    -webkit-column-count: 2;
	    			column-count		: 2;
				}
			}
			@media (min-width:1000px) {
				body>div.FrmAllTables {
				    -moz-column-count	: 3;
				    -webkit-column-count: 3;
	    			column-count		: 3;
				}
			}
		</style>
		<script>
		
		function openEditWindow(thisElem) {

			let data = document.querySelector('#jsObjKeys').dataset.keys;
			let objKeys = JSON.parse(data);
			console.log(objKeys);
			
			let frm = document.querySelector("#idEditForm");
			
			let key = thisElem.getAttribute('data-key');
			elemTr = document.querySelector('tr[data-key="'+key+'"]');
			elemsTd = elemTr.querySelectorAll('td[data-field');
			for (let i = 0; i < elemsTd.length; ++i) {
				let item = elemsTd[i];
				
				let fieldname = item.getAttribute('data-field');
				let elemFormInput = frm.querySelector('input[name="'+fieldname+'"]');
				
				if (elemFormInput) {
					elemFormInput.value = item.textContent;
				}
				
				if (fieldname in objKeys) {
					objKeys[fieldname] = item.textContent;
				}
				
			}

			let elemFormInput = frm.querySelector('input[name="<?=ParamNames::Key?>"]');	// php!
			elemFormInput.value = JSON.stringify(objKeys);
			
			document.querySelector("#idDivEditForm").classList.add("visible");
			
		}
		
		function openDeleteWindow(thisElem) {

			let data = document.querySelector('#jsObjKeys').dataset.keys;
			let objKeys = JSON.parse(data);
			console.log(objKeys);
			
			let frm = document.querySelector("#idDeleteForm");
			
			let key = thisElem.getAttribute('data-key');
			elemTr = document.querySelector('tr[data-key="'+key+'"]');
			elemsTd = elemTr.querySelectorAll('td[data-field');
			for (let i = 0; i < elemsTd.length; ++i) {
				let item = elemsTd[i];
				
				let fieldname = item.getAttribute('data-field');
				let elemFormInput = frm.querySelector('input[name="'+fieldname+'"]');
				
				if (elemFormInput) {
					elemFormInput.value = item.textContent;
				}
				
				if (fieldname in objKeys) {
					objKeys[fieldname] = item.textContent;
				}
				
			}

			let elemFormInput = frm.querySelector('input[name="<?=ParamNames::Key?>"]');	// php!
			elemFormInput.value = JSON.stringify(objKeys);
			
			document.querySelector("#idDivDeleteForm").classList.add("visible");
			
		}
		
		function sendForm(formId)
		{
			let elements = document.getElementById(formId);
			let formData = new FormData(); 
			for(let i=0; i<elements.length; i++) {
				formData.append(elements[i].name, elements[i].value);
			}
			let xmlHttp = new XMLHttpRequest();
			xmlHttp.onreadystatechange = function()	{
				if(xmlHttp.readyState == 4 && xmlHttp.status == 200) {
					if (xmlHttp.responseText.startsWith("<body")) {
						document.querySelector("body").outerHTML = xmlHttp.responseText;
					}
					else {
						let errorDiv = document.getElementById("idEditFormMessage");
						errorDiv.innerHTML = xmlHttp.responseText;
						errorDiv.classList.add("MessageWindow", "MessageWindowError");
					}
				}
			}
			xmlHttp.open("post", encodeURI(window.location.href)); 
			xmlHttp.send(formData); 
		}
         </script>

		</head>
		
		<? 

	}

}

function dump($object) {
	echo "<pre>";
	var_dump($object);
	echo "</pre>";
}

?>

