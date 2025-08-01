<?php
/*
	This is the place where the actual command is orchestrated.
	it ends up being our "main()"


*/
namespace ftrotter\DURC;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DURCWriteCommand extends Command{

    protected $signature = 'DURC:write {--squash} {--relation_config_file=} {--setting_config_file=} {--URLroot=}';
    protected $description = 'DURC:write generates Templates and Eloquent Classe, inferred from your DB structure';

    public function handle(){
	//what does this do?

	$is_debug = false;

	$firstPhaseGenerators = [
			'Eloquent Models' => 		'ftrotter\DURC\Generators\LaravelEloquentGenerator',
		];

	//second phase generators get the benifit of actually using the first-generation generators 
	//in their results... 	
	$secondPhaseGenerators = [
			'Web Controllers' => 		'ftrotter\DURC\Generators\LaravelControllerGenerator',
			'Mustache Edit Views' =>	'ftrotter\DURC\Generators\MustacheEditViewGenerator',
			'Mustache Index Page' => 	'ftrotter\DURC\Generators\MustacheIndexViewGenerator',
			'Laravel Routes' => 		'ftrotter\DURC\Generators\LaravelRouteGenerator',	
			'Test Routes' =>		'ftrotter\DURC\Generators\LaravelTestRouteGenerator',	
			'Mustache Menu' => 		'ftrotter\DURC\Generators\MustacheMenuGenerator',	
			'MySQLDump' => 			'ftrotter\DURC\Generators\MySQLDumpGenerator',	
			'Zermelo Index' => 		'ftrotter\DURC\Generators\ZermeloIndexGenerator',

		];


	$relation_config_file = $this->option('relation_config_file');

	if(!$relation_config_file){ //this option is not typically used... this is where things are saved by default...
		$relation_config_file = base_path()."/config/DURC_relation_config.edit_me.json"; //this is the user edited default config file..
	}

	$settings_config_file = $this->option('setting_config_file');

	if(!$settings_config_file){ //this option is not typically used... this is where things are saved by default...
		$settings_config_file = base_path()."/config/DURC_setting_config.json"; //this is the user edited default config file..
	}


	$squash = $this->option('squash');

	$URLroot = $this->option('URLroot');
	if(is_null($URLroot)){
		$URLroot = '/DURC/';
	}

	if($squash){
		echo "DURC:write Beginning code generation! Squashing all previous code generation \n";	
	}else{
		echo "DURC:write Beginning code generation!\n";
	}

	$relation_config = DURC::readDURCConfigJSON($relation_config_file);
	$settings_config = DURC::readDURCConfigJSON($settings_config_file);


	//each generator handles the creation of different type of file...
	//for the first phase...
	foreach($firstPhaseGenerators as $generator_label => $this_generator){
		if($is_debug){
			echo "Phase 1: Generating $generator_label...\t\t\t\n";
		}
		$this->run_one_generator($relation_config,$settings_config,$this_generator);
		echo "Phase 1: Finished $generator_label... \n";	
	}

	//each generator handles the creation of different type of file...
	//for the first phase...
	foreach($secondPhaseGenerators as $generator_label => $this_generator){
		if($is_debug){
			echo "Phase 2: Generating $generator_label...\t\t\t\n";
		}
		$this->run_one_generator($relation_config,$settings_config,$this_generator);
		echo "Phase 2: Finished $generator_label... \n";	
	}

	echo "DURC:write all done.\n";

//	$has_many_struct = DURC::getHasMany($db_struct);
//	$belongs_to_struct = DURC::getHasMany($db_struct);
	
    }


	private function run_one_generator($relation_config,$settings_config,$this_generator){

		$squash = true; //TODO where should this come from???
					//why it missing... wtf is going on?
		$URLroot = '/DURC/';
		$this_generator::start($relation_config,$squash,$URLroot);

		foreach($relation_config as $this_db => $db_data){
			foreach($db_data as $this_class_name => $table_data){
	
		
				$this_table = $this->_get_or_null($table_data,'table_name');


				$column_data = $this->_get_or_null($table_data,'column_data');
				if(is_null($column_data)){
					echo "Error: $this_db.$this_table ($this_class_name) did not have columns defined\n";
					exit(1);
				}

				$has_many = $this->_get_or_null($table_data,'has_many');
                		$has_one = $this->_get_or_null($table_data,'has_one');
				$belongs_to = $this->_get_or_null($table_data,'belongs_to');
				$many_many = $this->_get_or_null($table_data,'many_many');
				$many_through = $this->_get_or_null($table_data,'many_through');
				$create_table_sql = $this->_get_or_null($table_data,'create_table_sql');


				//we need to have access to all of the information from the other table as we consider many to many relations...
				if(is_array($has_many)){
					foreach($has_many as $table_type => $has_many_data){
						$from_db = $has_many_data['from_db'];
						$from_table = $has_many_data['from_table'];
						$has_many[$table_type]['other_columns'] = $relation_config[$from_db][strtolower($from_table)]['column_data'];
					}
				}

                		if(is_array($has_one)){
                    			foreach($has_one as $table_type => $has_one_data){
                        			$from_db = $has_one_data['from_db'];
                        			$from_table = $has_one_data['from_table'];
                        			$has_one[$table_type]['other_columns'] = $relation_config[$from_db][strtolower($from_table)]['column_data'];
                    			}
                		}

				//we need to have access to all of the information from the other table as we consider many to many relations...
				if(is_array($belongs_to)){
					foreach($belongs_to as $table_type => $belongs_to_data){
						$to_db = $belongs_to_data['to_db'];
						$to_table = $belongs_to_data['to_table'];
						$belongs_to[$table_type]['other_columns'] = $relation_config[$to_db][strtolower($to_table)]['column_data'];
					}
				}

				$data_for_gen = [
						'class_name' => $this_class_name,
						'database' => $this_db,
						'table' => $this_table,
						'fields' => $column_data,
						'has_many' => $has_many,
						'has_one' => $has_one,
						'belongs_to' => $belongs_to,
						'many_many' => $many_many,
						'many_through' => $many_through,
						'squash' => $squash,
						'URLroot' => $URLroot,
						'create_table_sql' => $create_table_sql
					];

				$this_table_settings = $settings_config[$this_db][$this_table];

				//add all of the setting configuration variables to the other data passed to run_generator
				foreach($this_table_settings as $config_var => $config_value){
					$data_for_gen[$config_var] = $config_value;
				}


				$generated_results = $this_generator::run_generator($data_for_gen);
			}
		}

		$this_generator::finish($relation_config,$squash,$URLroot);
		//echo "done\n";

	}


	private function _get_or_null($array,$key){
		if(isset($array[$key])){
			return($array[$key]);
		}else{
			return(null);
		}
	}


}
