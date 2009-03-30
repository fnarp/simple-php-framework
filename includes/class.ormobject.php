<?PHP
	class ORMObject extends DBObject
    {
		protected $belongsTo    = array();
		protected $hasOne       = array();
		protected $hasMany      = array();
		protected $hasManyIds   = array();
		protected $hasManyCount = array();

        public function __construct($table_name, $columns, $id = null)
        {
            parent::__construct($table_name, $columns, $id);
    	}

        public function __get($key)
        {
			$value = @parent::__get($key);
			if(!is_null($value))
				return $value;

			if(array_key_exists($key, $this->belongsTo))
				return $this->getBelongsTo($key);

			if(array_key_exists($key, $this->hasOne))
				return $this->getHasOne($key);

			if(array_key_exists($key, $this->hasMany))
				return $this->getHasMany($key);

			if(array_key_exists($key, $this->hasManyIds))
				return $this->getHasManyIds($key);

			if(array_key_exists($key, $this->hasManyCount))
				return $this->getHasManyCount($key);

			$trace = debug_backtrace();
			trigger_error("Undefined property via ORMObject::__get(): $key in {$trace[0]['file']} on line {$trace[0]['line']}", E_USER_NOTICE);
            return null;
        }

        public function __set($key, $value)
        {
            if(array_key_exists($key, $this->columns))
                $this->columns[$key] = $value;
			elseif(array_key_exists($key, $this->belongsTo))
				$this->setBelongsTo($key, $value);
			elseif(array_key_exists($key, $this->hasOne))
				$this->setHasOne($key, $value);
			elseif(array_key_exists($key, $this->hasMany))
				$this->setHasMany($key, $value);

            return $value;
        }

		// To be made in the object with the foreign key
		public function belongsTo($class_name, $primary_key = null, $foreign_key = null)
		{
			if(is_null($primary_key))
				$primary_key = 'id';
			
			if(is_null($foreign_key))
				$foreign_key = strtolower($class_name . '_id');
		
			// lcfirst() in PHP 5.3
			$lcf_class_name = $class_name;
			$lcf_class_name[0] = strtolower($lcf_class_name[0]);
			
			$this->belongsTo[$lcf_class_name] = array('class_name' => $class_name, 'primary_key' => $primary_key, 'foreign_key' => $foreign_key);
		}
		
		protected function getBelongsTo($key)
		{
			$obj = new $this->belongsTo[$key]['class_name'];
			$pk = $this->belongsTo[$key]['primary_key'];
			$fk = $this->belongsTo[$key]['foreign_key'];
			$obj->select($this->$fk, $pk);
			return is_null($obj->id) ? null : $obj;
		}
		
		protected function setBelongsTo($key, $val)
		{
			if(!is_subclass_of($val, 'DBObject'))
			{
				trigger_error("Cannont assign non-DBObject to ORMObject property $key in {$trace[0]['file']} on line {$trace[0]['line']}", E_USER_NOTICE);
				return;
			}

			$pk = $this->belongsTo[$key]['primary_key'];
			$fk = $this->belongsTo[$key]['foreign_key'];
			$this->$fk = $val->$pk;
			$this->update();
		}
		
		// To be made in the object with the primary key
		public function hasOne($class_name, $primary_key = null, $foreign_key = null)
		{
			if(is_null($primary_key))
				$primary_key = 'id';
			
			if(is_null($foreign_key))
				$foreign_key = strtolower($class_name . '_id');

			// lcfirst() in PHP 5.3
			$lcf_class_name = $class_name;
			$lcf_class_name[0] = strtolower($lcf_class_name[0]);

			$this->hasOne[$lcf_class_name] = array('class_name' => $class_name, 'primary_key' => $primary_key, 'foreign_key' => $foreign_key);
		}

		protected function getHasOne($key)
		{
			$obj = new $this->hasOne[$key]['class_name'];
			$pk = $this->hasOne[$key]['primary_key'];
			$fk = $this->hasOne[$key]['foreign_key'];
			$obj->select($this->$pk, $fk);
			return is_null($obj->id) ? null : $obj;
		}
		
		protected function setHasOne($key, $val)
		{
			if(!is_subclass_of($val, 'DBObject'))
			{
				trigger_error("Cannont assign non-DBObject to ORMObject property $key in {$trace[0]['file']} on line {$trace[0]['line']}", E_USER_NOTICE);
				return;
			}

			$pk = $this->hasOne[$key]['primary_key'];
			$fk = $this->hasOne[$key]['foreign_key'];
			$val->$fk = $this->$pk;
			$val->update();
		}
		
		public function hasMany($class_name, $primary_key = null, $foreign_key = null)
		{
			// TODO: cond, order, through
			
			if(is_null($primary_key))
				$primary_key = 'id';
			
			if(is_null($foreign_key))
				$foreign_key = strtolower($this->className . '_id');
				
			// lcfirst() in PHP 5.3
			$lcf_class_name = $class_name;
			$lcf_class_name[0] = strtolower($lcf_class_name[0]);
			
			$this->hasMany[$lcf_class_name . 's'] = array('class_name' => $class_name, 'primary_key' => $primary_key, 'foreign_key' => $foreign_key);
			$this->hasManyIds[$lcf_class_name . 'Ids'] = array('class_name' => $class_name, 'primary_key' => $primary_key, 'foreign_key' => $foreign_key);
			$this->hasManyCount[$lcf_class_name . 'Count'] = array('class_name' => $class_name, 'primary_key' => $primary_key, 'foreign_key' => $foreign_key);
		}
		
		protected function getHasMany($key)
		{
			$db = Database::getDatabase();

			$pk = $this->hasMany[$key]['primary_key'];
			$fk = $this->hasMany[$key]['foreign_key'];
			$id = $this->$pk;

			$tmp_obj = new $this->hasMany[$key]['class_name'];

			$sql = "SELECT * FROM `{$tmp_obj->tableName}` WHERE `$fk` = $id";
			$objs = DBObject::glob($this->hasMany[$key]['class_name'], $sql);
			return $objs;
		}
		
		protected function setHasMany($key, $value)
		{
			if(!is_array($value))
			{
				trigger_error("Assignment value must be an array for ORMObject property $key in {$trace[0]['file']} on line {$trace[0]['line']}", E_USER_NOTICE);
				return;
			}
			
			if(count($value) == 0)
				return;

			$pk = $this->hasMany[$key]['primary_key'];
			$fk = $this->hasMany[$key]['foreign_key'];
			$id = $this->$pk;
			$id_col = $v->idColumnName;

			$in_str = array();
			foreach($value as $v)
				$in_str[] = $v->$id_col;
			$in_str = "'" . implode("', '", $in_str) . "'";
			
			$sql = "UPDATE `{$v->tableName}` SET `$fk` = '$id' WHERE `$id_col` IN ($in_str)";
			$db->query($sql);
		}
		
		protected function getHasManyIds($key)
		{
			$db = Database::getDatabase();

			$pk = $this->hasOne[$key]['primary_key'];
			$fk = $this->hasOne[$key]['foreign_key'];
			$id = $this->$pk;

			$tmp_obj = new $this->hasMany[$key]['class_name'];

			return $db->getValues("SELECT `{$tmp_obj->idColumnName}` FROM `{$tmp_obj->tableName}` WHERE `$fk` = '$id'");
		}

		protected function getHasManyCount($key)
		{
			$db = Database::getDatabase();

			$pk = $this->hasOne[$key]['primary_key'];
			$fk = $this->hasOne[$key]['foreign_key'];
			$id = $this->$pk;

			$tmp_obj = new $this->hasMany[$key]['class_name'];

			return $db->getValue("SELECT COUNT(*) FROM `{$tmp_obj->tableName}` WHERE `$fk` = '$id'");
		}
	}
