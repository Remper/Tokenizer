<?php

namespace Tokenizer;

/**
 * Синглтон для работы с базой данных
 * @author Ярослав Нечаев <mail@remper.ru>
 */
class Database {
    protected static $instance;  //Инстанс
    //Кешированные реквизиты
    private $user = '';
	private $pass = '';
	private $db = '';
	//Соединение
	private $conn;

//////
// Функции обслуживания соединения
//////
	
	/**
	 * Вернуть экземпляр класса
	 * 
	 * @return Database
	 */
    public static function getDB() {
        if ( is_null(self::$instance) ) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
	
	/**
	 * Подключиться к базе данных
	 * 
	 * @param string $user Имя пользователя
	 * @param string $pass Пароль
	 * @param string $db DSN для обращения
	 * @throws Exception
	 */
    public function connect($user, $pass, $db) {
    	$this->user = $user;
		$this->pass = $pass;
		$this->db = $db;
		
		try {
			//Инициализируем объект базы данных
			$this->conn = new \PDO($this->db,  $this->user, $this->pass);
			$this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			//Устанавливаем дефолтную кодировку
			$sth = $this->conn->query('SET NAMES utf8');
		} catch (\PDOException $e) {
            var_dump($e);
			throw new \Exception("Невозможно подключиться к базе данных");
		}
	}
	
	/**
	 * Проверить подключение к базе
	 * 
	 * @return bool Подключены ли мы к базе
	 */
	public function isConnected() {
		try {
			$sth = $this->conn->query('SHOW TABLES');
		} catch (\PDOException $e) {
			return false;
		}
	}
	
	/**
	 * Переподключиться, если соединение разорвано
	 * 
	 * @return bool результат переподключения
	 * @throws Exception
	 */
	public function reconnect() {
		if (!$this->isConnected()) {
			if ($this->user === '')
				return false;
			
			$this->connect($this->user, $this->pass, $this->db);
		}
		return true;
	}
	
//////
// Геттеры
//////

    /**
     * Получить сырые данные морфологического анализа из базы данных
     *
     * @param int $senid ID предложения
     * @return array Сырые данные
     */
    public function getRawData($senid) {
        $result = $this->ExecuteQuery("
			SELECT
				`id`, `sen_id`, `data`
			FROM `raw_signature`
			WHERE `sen_id` = :senid
		", array(
            array(":senid", $senid, \PDO::PARAM_INT)
        ));

        return $result->fetch(\PDO::FETCH_ASSOC);
    }

	/**
	 * Получить массив коэффициентов для всех известных векторов
	 * 
	 * @return array Ассоциативный массив коэффициентов vector => coeff
	 * @throws Exception
	 */
	public function getTokenCoeff() {
		$result = $this->ExecuteQuery("
			SELECT
				`vector`, `coeff`
			FROM `coeffs`
		");
		
		$res = array();
		while ($row = $result->fetch(\PDO::FETCH_ASSOC))
			array_push($res, $row);
		return $res;
	}
	
	/**
	 * Получить массив параграфов по ID текста
	 * 
	 * @param int $textid ID текста
	 * @return array Ассоциативный массив параграфов из базы
	 * @throws Exception
	 */
	public function getParagraphs($textid) {
		$result = $this->ExecuteQuery("
			SELECT
				`id`, `text_id`, `order`, `text`
			FROM `sentences`
			WHERE `text_id` = :textid
			", array(
				array(":textid", $textid, \PDO::PARAM_INT)
			)
		);
		
		$res = array();
		while ($row = $result->fetch(\PDO::FETCH_ASSOC))
			array_push($res, $row);
		return $res;
	}
	 
	/**
	 * Получить массив предложений по ID параграфа
	 * 
	 * @param int $parid ID параграфа
	 * @return array Ассоциативный массив предложений из базы
	 * @throws Exception
	 */
	public function getSentences($parid) {
		$result = $this->ExecuteQuery("
			SELECT
				`id`, `par_id`, `order`, `text`
			FROM `sentences`
			WHERE `par_id` = :parid
			", array(
				array(":parid", $parid, \PDO::PARAM_INT)
			)
		);
		
		$res = array();
		while ($row = $result->fetch(\PDO::FETCH_ASSOC))
			array_push($res, $row);
		return $res;
	}

    /**
     * Получить все тексты
     *
     * @param $start С какого текста возвращать результат
     * @param $limit Максимальное количество результатов
     * @return array Массив результатов
     */
    public function getAllTexts($start = 0, $limit = 1000) {
        $result = $this->ExecuteQuery("
            SELECT `id`, `text`, `opinion`, `wordcount`
            FROM `texts`
            LIMIT :start, :limit
        ", array(
            array(":start", $start, \PDO::PARAM_INT),
            array(":limit", $limit, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            array_push($res, $row);
        return $res;
    }

    /**
     * Получить все токены по тексту
     *
     * @param int $textid ID текста
     * @return Token[] Массив результатов
     */
    public function getTokensByTextID($textid) {
        $result = $this->ExecuteQuery("
            SELECT `id`, `sen_id`, `order`, `text`, `lemma_id`, `form_id`, `checked`, `method`
            FROM `tokens`
            WHERE `text_id` = :textid
        ", array(
            array(":textid", $textid, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            array_push($res, new Token($row["text"], $row["sen_id"], $row["order"], $row["lemma_id"], $row["form_id"], $row["checked"], $row["method"]));
        }
        return $res;
    }

    public function getHighestWordcount() {
        $result = $this->ExecuteQuery("
            SELECT `wordcount`
            FROM `texts` ORDER BY `wordcount` DESC
            LIMIT 0, 1
        ");

        $row  = $result->fetch(\PDO::FETCH_ASSOC);
        return (int) $row["wordcount"];
    }

    /**
     * Получить все тексты, полезные для обучения
     *
     * @param $start С какого текста возвращать результат
     * @param $limit Максимальное количество результатов
     * @return Text[] Массив результатов
     */
    public function getAllValuableTexts($start = 0, $limit = 1000) {
        $result = $this->ExecuteQuery("
            SELECT `id`, `text`, `opinion`, `wordcount`
            FROM `texts`
            WHERE `opinion` <> 0 AND `wordcount` <> 0
            LIMIT :start, :limit
        ", array(
            array(":start", $start, \PDO::PARAM_INT),
            array(":limit", $limit, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            array_push($res, new Text($row["text"], (int) $row["id"], (int) $row["opinion"], $row["wordcount"]));
        return $res;
    }

    public function getValuableTextsCount() {
        $result = $this->ExecuteQuery("
            SELECT COUNT(*)
            FROM `texts`
            WHERE `opinion` <> 0 AND `wordcount` <> 0
        ");

        $row = $result->fetch(\PDO::FETCH_NUM);
        return $row[0];
    }

    /**
     * @param Token $token
     */
    public function getTextsCountWithToken($token) {
        $result = $this->ExecuteQuery("
            SELECT COUNT(DISTINCT b.id)
            FROM tokens AS a
            INNER JOIN texts AS b ON a.text_id = b.id
            WHERE a.lemma_id = :lemma_id AND a.form_id = :form_id
        ", array(
            array(":lemma_id", $token->getLemmaId(), \PDO::PARAM_INT),
            array(":form_id", $token->getFormId(), \PDO::PARAM_INT)
        ));

        $row = $result->fetch(\PDO::FETCH_NUM);
        return $row[0];
    }

    /**
     * @param int $start
     * @param int $limit
     * @return Token[]
     */
    public function getTokensFromValuableTexts($start = 0, $limit = 1000) {
        $result = $this->ExecuteQuery("
            SELECT
                COUNT(DISTINCT a.`text_id`) AS `count`, a.`id`, a.`sen_id`, a.`order`, a.`text`, a.`lemma_id`, a.`form_id`, a.`checked`, a.`method`
            FROM tokens AS a
            INNER JOIN texts AS b ON a.text_id = b.id
            WHERE b.opinion <> 0 AND b.wordcount <> 0
            GROUP BY a.`lemma_id`, a.`form_id`
            LIMIT :start, :limit
        ", array(
            array(":start", $start, \PDO::PARAM_INT),
            array(":limit", $limit, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            array_push($res, array(
                "token" => new Token($row["text"], $row["sen_id"], $row["order"], $row["lemma_id"], $row["form_id"], $row["checked"], $row["method"]),
                "count" => $row["count"]
            ));
        }
        return $res;
    }

    /**
     * Получить все предложения
     *
     * @param $start С какого предложения возвращать результат
     * @param $limit Максимальное количество результатов
     * @return array Массив результатов
     */
    public function getAllSentences($start = 0, $limit = 1000) {
        $result = $this->ExecuteQuery("
            SELECT `id`, `par_id`, `order`, `text`
            FROM `sentences`
            LIMIT :start, :limit
        ", array(
            array(":start", $start, \PDO::PARAM_INT),
            array(":limit", $limit, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            array_push($res, $row);
        return $res;
    }

    /**
     * Получить для которых не посчитаны токены
     *
     * @param $start С какого предложения возвращать результат
     * @param $limit Максимальное количество результатов
     * @return array Массив результатов
     */
    public function getAllSentWithoutTokens($start = 0, $limit = 1000) {
        $result = $this->ExecuteQuery("
            SELECT a.id AS `id`, a.par_id, a.order, a.text, c.id AS `text_id`
            FROM `sentences` AS a
            INNER JOIN `raw_signature` AS d ON a.id = d.sen_id
            INNER JOIN `paragraphs` AS b ON a.par_id = b.id
            INNER JOIN `texts` AS c ON b.text_id = c.id
            WHERE c.wordcount = 0
            LIMIT :start, :limit
        ", array(
            array(":start", $start, \PDO::PARAM_INT),
            array(":limit", $limit, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            array_push($res, $row);
        return $res;
    }

    public function countForAllSentWithoutTokens() {
        $result = $this->ExecuteQuery("
            SELECT COUNT(*)
            FROM `sentences` AS a
            INNER JOIN `raw_signature` AS d ON a.id = d.sen_id
            INNER JOIN `paragraphs` AS b ON a.par_id = b.id
            INNER JOIN `texts` AS c ON b.text_id = c.id
            WHERE c.wordcount = 0
        ");

        $row = $result->fetch(\PDO::FETCH_NUM);
        return $row[0];
    }

    public function getAllSentencesWithData($start = 0, $limit = 1000) {
        $result = $this->ExecuteQuery("
            SELECT a.`id`, a.`par_id`, a.`order`, a.`text`, b.`data`
            FROM `sentences` AS a
            LEFT JOIN `raw_signature` AS b ON a.id = b.sen_id
            LIMIT :start, :limit
        ", array(
            array(":start", $start, \PDO::PARAM_INT),
            array(":limit", $limit, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            array_push($res, $row);
        return $res;
    }

    /**
     * Найти все граммемы с заданным parent ID
     *
     * @param int $parent parent ID
     */
    public function findGrammemsByParent($parent = 0) {
        $result = $this->ExecuteQuery("
            SELECT * FROM `grammems`
            WHERE `parentid` = :parent
        ", array(
            array(":parentid", $parent, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            array_push($res, new Grammem($row["id"], $parent, $row["name"]));
        return $res;
    }

    /**
     * Найти форму леммы с заданным текстом
     *
     * @param int $lemmid ID леммы
     * @param string $text Текст
     * @return int ID формы
     */
    public function findFormOfLemma($lemmid, $text) {
        if ($lemmid == 0)
            return 0;

        $result = $this->ExecuteQuery("
            SELECT `formid` FROM `lemmas`
            WHERE `lemmid` = :lemmid AND `text` = :text
        ", array(
            array(":lemmid", $lemmid, \PDO::PARAM_INT),
            array(":text", $text, \PDO::PARAM_STR)
        ));

        $row = $result->fetch(\PDO::FETCH_ASSOC);
        return $row["formid"];
    }

    /**
     * Найти все формы с заданным текстом
     *
     * @param string $text Текст
     * @return Form[] формы
     */
    public function findLemma($text) {
        $result = $this->ExecuteQuery("
            SELECT `lemmid`, `formid`, `text`, `grammems` FROM `lemmas`
            WHERE `text` = LOWER(:text)
        ", array(
            array(":text", $text, \PDO::PARAM_STR)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC))
            array_push($res, new Form($row["lemmid"], $row["formid"], $row["text"], $row["grammems"]));
        return $res;
    }

//////
// Функции поиска
//////

    /**
	 * Существует ли заданный токен в базе
	 * 
	 * @param string $token Токен
	 * @return bool Результат запроса
	 * @throws Exception
	 */
	public function isTokenExists($token) {
		$result = $this->ExecuteQuery("
			SELECT
				count(*) AS ccc
			FROM `tokens`
			WHERE `lemma_id` <> 0 AND `text` = :token
			", array(
				array(":token", $token, \PDO::PARAM_STR)
			)
		);
		$result = $result->fetch(\PDO::FETCH_ASSOC);
		return (bool) $result['ccc'];
	}
	
//////
// Функции сохранения сущностей в базу
//////
	
	/**
	 * Сохранить текст в базу данных
	 * 
	 * @param Text $text текст для сохранения
	 * @return int ID текста
	 * @throws Exception
	 */
	public function saveText($text) {
		$this->ExecuteQuery("
			INSERT INTO
				`texts`
			(`id`, `text`, `opinion`, `wordcount`)
			VALUES (NULL, :text, :opinion, :wordcount)
			", array(
				array(":text", $text->getText(), \PDO::PARAM_STR),
                array(":opinion", $text->getOpinion(), \PDO::PARAM_INT),
                array(":wordcount", $text->getWordcount(), \PDO::PARAM_INT)
			)
		);
		$result = $this->ExecuteQuery("
			SELECT last_insert_id() AS `id`
		");
		$result = $result->fetch(\PDO::FETCH_ASSOC);
		return $result['id'];
	}
	
	/**
	 * Сохранить параграф в базу данных
	 * 
	 * @param Paragraph $paragraph Параграф для сохранения
	 * @return int ID параграфа
	 * @throws Exception
	 */
	public function saveParagraph($paragraph) {
		$this->ExecuteQuery("
			INSERT INTO
				`paragraphs`
			(`id`, `text_id`, `order`, `text`)
			VALUES (NULL, :textid, :order, :text)
			", array(
				array(":textid", $paragraph->getParentID(), \PDO::PARAM_INT),
				array(":order", $paragraph->getOrder(), \PDO::PARAM_INT),
				array(":text", $paragraph->getText(), \PDO::PARAM_STR)
			)
		);
		$result = $this->ExecuteQuery("
			SELECT last_insert_id() AS `id`
		");
		$result = $result->fetch(\PDO::FETCH_ASSOC);
		return $result['id'];
	}
	
	/**
	 * Сохранить приложение в базу данных
	 * 
	 * @param Sentence $sentence Приложение для сохранения
	 * @return int ID предложения
	 * @throws Exception
	 */
	public function saveSentence($sentence) {
		$this->ExecuteQuery("
			INSERT INTO
				`sentences`
			(`id`, `par_id`, `order`, `text`)
			VALUES (NULL, :parid, :order, :text)
			", array(
				array(":parid", $sentence->getParentID(), \PDO::PARAM_INT),
				array(":order", $sentence->getOrder(), \PDO::PARAM_INT),
				array(":text", $sentence->getText(), \PDO::PARAM_STR)
			)
		);
		$result = $this->ExecuteQuery("
			SELECT last_insert_id() AS `id`
		");
		$result = $result->fetch(\PDO::FETCH_ASSOC);
		return $result['id'];
	}
	
	/**
	 * Сохранить токен в базу данных
	 * 
	 * @param Token $token Токен для сохранения
	 * @return int ID токена
	 * @throws Exception
	 */
	public function saveToken($token) {
		$this->ExecuteQuery("
			INSERT INTO
				`tokens`
			(`id`, `sen_id`, `text_id`, `order`, `text`, `lemma_id`, `form_id`, `checked`, `method`)
			VALUES (NULL, :senid, :text_id, :order, :text, :lemma_id, :form_id, :checked, :method)
			", array(
				array(":senid", $token->getParentId(), \PDO::PARAM_INT),
                array(":text_id", $token->getTextId(), \PDO::PARAM_INT),
				array(":order", $token->getOrder(), \PDO::PARAM_INT),
				array(":text", $token->getText(), \PDO::PARAM_STR),
				array(":lemma_id", $token->getLemmaId(), \PDO::PARAM_INT),
                array(":form_id", $token->getFormId(), \PDO::PARAM_INT),
				array(":checked", (int) $token->isChecked(), \PDO::PARAM_INT),
				array(":method", $token->getMethod(), \PDO::PARAM_INT)
			)
		);
		$result = $this->ExecuteQuery("
			SELECT last_insert_id() AS `id`
		");
		$result = $result->fetch(\PDO::FETCH_ASSOC);
		return $result['id'];
	}

//////
// Функции для заполнения базы данных
//////
	
	/**
	 * Добавить лемму в базу данных
	 * 
	 * @param int $id ID леммы
	 * @param int $formid ID словоформы в лемме
	 * @param string $name Имя леммы
	 * @param string $grammems Сериализованный в JSON массив граммем
	 */
	public function addLemma($id, $formid, $text, $grammems) {
		$this->ExecuteQuery("
			INSERT INTO
				`lemmas`
			(`lemmid`, `formid`, `text`, `grammems`)
			VALUES (:lemmid, :formid, :text, :grammems)
			", array(
				array(":lemmid", $id, \PDO::PARAM_INT),
				array(":formid", $formid, \PDO::PARAM_INT),
				array(":text", $text, \PDO::PARAM_STR),
				array(":grammems", $grammems, \PDO::PARAM_STR)
			)
		);
		return true;
	}
	
	/**
	 * Добавить граммему в базу данных
	 * 
	 * @param int $id ID граммемы
	 * @param int $parentid ID родителя граммемы
	 * @param string $name Имя граммемы
	 */
	public function addGrammem($parentid, $name) {
		$this->ExecuteQuery("
		    INSERT INTO
		        `grammems`
		    (`parentid`, `name`)
		    VALUES (:parentid, :name)
		    ", array(
                array(":parentid", $parentid, \PDO::PARAM_INT),
                array(":name", $name, \PDO::PARAM_STR)
            )
        );
        $result = $this->ExecuteQuery("
            SELECT last_insert_id() AS `id`
        ");
        $result = $result->fetch(\PDO::FETCH_ASSOC);
        return $result['id'];
	}

    /**
     * Добавить сырые данные в базу данных
     *
     * @param integer $sen_id ID предложения
     * @param string $data Данные
     * @return bool Результат
     */
    public function addRawData($sen_id, $data) {
        $this->ExecuteQuery("
		    INSERT INTO
		        `raw_signature`
		    (`sen_id`, `data`)
		    VALUES (:senid, :data)
		    ", array(
                array(":senid", $sen_id, \PDO::PARAM_INT),
                array(":data", $data, \PDO::PARAM_STR)
            )
        );
        return true;
    }

    public function getTextsWithWordcounts($start, $limit) {
        $result = $this->ExecuteQuery("
          SELECT a.id, COUNT(b.id) AS `count`
          FROM texts AS a
          INNER JOIN tokens AS b ON a.id = b.text_id
          GROUP BY a.id
          LIMIT :start, :limit
        ", array(
            array(":start", $start, \PDO::PARAM_INT),
            array(":limit", $limit, \PDO::PARAM_INT)
        ));

        $res = array();
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            array_push($res, $row);
        }
        return $res;
    }

    public function setTextWordcount($textid, $count) {
        $this->ExecuteQuery("
		    UPDATE texts
		    SET wordcount = :count
		    WHERE id = :id
		    ", array(
                array(":id", $textid, \PDO::PARAM_INT),
                array(":count", $count, \PDO::PARAM_STR)
            )
        );
        return true;
    }
	
//////
// Служебная фигня
//////
	
	/**
	 * Выполнить запрос в базу данных
	 * 
	 * @param string $query Запрос к базе
	 * @param array $params Параметры запроса
	 * @return \PDOStatement Объект запроса в базу
	 * @throws \PDOException
	 */
	public function ExecuteQuery($query, $params = array()) {
		$dbo = $this->conn->prepare($query);
		foreach($params as $param) {
			$dbo->bindValue($param[0], $param[1], $param[2]);
		}
		$dbo->execute();
		
		return $dbo;
	}
	
    private function __construct() {}
    private function __clone()     {}
    private function __wakeup()    {}
}

?>