<?php

namespace Tokenizer;

/**
 * Класс-сущность и интерфейсы
 * @author Ярослав Нечаев <mail@remper.ru>
 */
abstract class Entity {
	private $id;
	private $text;
	
	abstract public function save();

	/**
	 * Получить текст
	 */
	public function getText() {
		return $this->text;
	}	
	
	/**
	 * Сохранена ли сущность в базу данных
	 */
	public function isSaved() {
		return (bool) $this->id;
	}
}

interface TRPiece {
	public function getOrder();
	public function getParentId();
	public function setParentId($id);
}

interface TRWhole {
	public function split();
	public function getPieces();
}
 
?>