<?php

namespace nkizza\simplerelations\widgets;

use yii\widgets\InputWidget;
use yii\helpers\Html;
use yii\base\InvalidConfigException;

class RelationWidget extends InputWidget {
	
	public $fields;
	public $relation;
	public $condition; //дополн. условие для отбора
	public $fieldOptions = [];
	public $labels = false; //пока решение для лейблов в инлайн-форме

	public function init() {
		$this->registerScripts();
		$this->prettifyFields();
		$this->fieldOptions = array_merge(['class' => 'form-inline mb10'], $this->fieldOptions);
	}

	private function prettifyField($field) {
		if(!is_array($field)) $field = ['attribute' => $field];
		if(!isset($field['method'])) 
			$field['method'] = "textInput";
		if(!isset($field['options']))
			$field['options'] = [];
		if(!isset($field['items']))
			$field['items'] = [];
		if(!isset($field['label']))
			$field['label'] = $this->model->getAttributeLabel($this->relation.".".$field['attribute']);
		
		return (object) $field;
	}
	private function prettifyFields() {
		
		foreach($this->fields as $key => $field) {
			if(isset($field['wrapper'])) {
				$this->fields[$key] = (object) $field;
			} else {
				$this->fields[$key] = $this->prettifyField($field);	
			}
		}
	} 

	public function registerScripts() {
		$this->id = $this->id ?: $relation."_rw";
		$id = $this->id; 
		
		$this->view->registerCss("
			.mb10 {margin-bottom:10px;}
			#$id .template {display:none;}
		");
		$this->view->registerJs("
			var ".$id."_addClick = function() {
				var _template = $('#$id [data-relitem].template').clone(true);
				var _key = $('#$id [data-relitem]:not(.template)').last().attr('data-key'); if(!_key) _key = 0; else _key++;
				_template.removeClass('template');
				_template.attr('data-key',_key);
				_template.find(':input').each(function(index, element) {
					_name = $(element).prop('name').replace('{{KEY}}', _key);
					$(element).prop('name', _name);  
					$(element).prop('disabled', false);
					$(element).prop('id', '$id'+'_'+index+'_'+_key);
				});
				$('#$id .add').before(_template);
				return false;
			};
			var ".$id."_deleteClick = function() {
				if(confirm('Do you want to remove the element?')) $(this).parent().remove();
				return false;
			};
			$(function() {
				$('#$id .add .btn').click(".$id."_addClick);
				$('#$id .form-inline .btn.delete').click(".$id."_deleteClick);
			});
		");
	}	

	private function renderField($field, $key, $value, $disabled = false) {
		$mclass = substr($this->model->className(), strrpos($this->model->className(), "\\")+1);
		
		$label = isset($field->label) ? $field->label : $this->model->getAttributeLabel($this->relation.".".$field->attribute);
		$fieldName = "$mclass"."[".$this->attribute."][$key][".$field->attribute."]";
		
		$method = $field->method;
		$options = ['class' => 'form-control', 'empty' => $label, 'id' => $this->id.'_'.$field->attribute.'_'.$key, 'data-attr' => $field->attribute]; 
		if($disabled) $options['disabled'] = true;
		
		switch ($method) {
			case 'dropDownList':
				$input = Html::$method($fieldName, $value, $field->items, $options);
				break;
			case 'checkbox':
				$input = Html::$method($fieldName, $value, $options);
				break;
			default:
				$input = Html::$method($fieldName, $value, $options);
				break;
		}
			
		return ($method == 'hiddenInput')
			? $input
			: Html::tag('div', $input, ['class' => 'form-group']);
	}
	
	private function renderWrapper($wrapper, $key, $model = null, $disabled = false) {
		$contentWrapper = $wrapper->html;
		$content = "";
		foreach($wrapper->fields as $field) {
			$field = $this->prettifyField($field);
			$value = (!empty($model)) ? $model->getAttribute($field->attribute) : "";
			$label = ($field->method == 'hiddenInput') ? "" : Html::label($field->label);
			$content .= Html::tag('li', $label.$this->renderField($field, $key, $value, $disabled));
		}
		$options = (isset($wrapper->wrapperOptions)) ? $wrapper->wrapperOptions : [];
		$options = array_merge(['class' => 'form-group'], $options); 
		return Html::tag('div', str_replace('{{content}}', $content, $contentWrapper), $options);
	}
	
	private function renderLabel($field) {
		$label = isset($field->label) ? $field->label : $this->model->getAttributeLabel($this->relation.".".$field->attribute);
		return Html::tag('div', Html::label($label), ['class' => 'form-group']);		
	}

	public function run() {
		if(empty($this->model) || empty($this->attribute))
			throw new InvalidConfigException("Model and attribute should be specified.");
		if(empty($this->fields) || !is_array($this->fields))
			throw new InvalidConfigException("Invalid fields configuration.");
		if(empty($this->relation))
			throw new InvalidConfigException("Relation name should be specified.");
			
		//вернуть список форм + кнопку добавления новой формы и зарегистрировать добавление
		$relation = $this->model->getRelation($this->relation);
		if(!$relation)
			throw new InvalidConfigException("Invalid relation.");

		if(!empty($this->condition)) $relation->andWhere($this->condition);

		$relationClass = $relation->modelClass;
		$primaryKey = $relationClass::primaryKey();
		
		$html = "";
		
		//labels
		if($this->labels) {
			$template = "";
			foreach($this->fields as $field) {
				if($field->method=='hiddenInput') continue;
				$template .= $this->renderLabel($field);
			}
			$html .= Html::tag('div', $template, $this->fieldOptions);
		} 
		
		//template for cloning
		$template = "";
		foreach($primaryKey as $pk) $template .= $this->renderField((object) ['attribute' => $pk, 'method' => 'hiddenInput'], '{{KEY}}', '', true); 
		foreach($this->fields as $field) {
			$value = isset($field->value) ? $field->value : "";
			if($field->method == 'wrapper') $template .= $this->renderWrapper($field, '{{KEY}}', null, true); 
			else $template .= $this->renderField($field, '{{KEY}}', $value, true);
		}
		$template .= Html::button('<span class="glyphicon glyphicon-trash"></span>', ['class' => 'btn btn-danger delete']);
		
		$options = $this->fieldOptions; $options['class'] .= " template"; 
		$options['data-key'] = '{{KEY}}'; $options['data-relitem'] = true; 
		$html .= Html::tag('div', $template, $options);
		
		foreach($relation->all() as $key => $model) {
			$formGroup = "";
			foreach($primaryKey as $pk)
				$formGroup .= $this->renderField((object) ['attribute' => $pk, 'method' => 'hiddenInput'], $key, $model->$pk);
			foreach($this->fields as $field) {
				if($field->method == 'wrapper') $formGroup .= $this->renderWrapper($field, $key, $model); 
				else $formGroup .= $this->renderField($field, $key, $model->getAttribute($field->attribute));
			}
			$formGroup .= Html::button('<span class="glyphicon glyphicon-trash"></span>', ['class' => 'btn btn-danger delete']);
			
			$options = $this->fieldOptions; $options['data-key'] = $key; $options['data-relitem'] = true;
			$html .= Html::tag('div', $formGroup, $options);
		}
		
		$html .= Html::tag('div', Html::button('<span class="glyphicon glyphicon-plus"></span>', ['class' => 'btn btn-info']), ['class' => 'add']);
		
		$options = array_merge($this->options, ['id' => $this->id]);
		$html = Html::tag('div', $html, $options);
		
		return $html;
	}
}  

?>