<?php
/*
        This is the place where the actual command is orchestrated.
        it ends up being our "main()"


*/
namespace ftrotter\DURC\Generators;

use ftrotter\DURC\DURC;
use ftrotter\DURC\Signature;

class LaravelControllerGenerator extends \ftrotter\DURC\DURCGenerator {


        public static function start(
							$db_config,
                                                        $squash,
                                                        $URLroot){
                //does nothing need to comply with abstract class
        }

        public static function finish(
							$db_config,
                                                        $squash,
                                                        $URLroot){
                //does nothing need to comply with abstract class
        }

/*
	This accepts the data for each table in the database that DURC is aware of..
	and generates a Laravel Controller
*/
	public static function run_generator($data_for_gen){

		$data_for_gen = self::_check_arguments($data_for_gen); //ensure reasonable defaults...
		extract($data_for_gen); //everything goes into the local scope... this includes all settings from the config json file...

		//terrible idea. Forces contstant git changes for no actual code change.
                $gen_string = DURC::get_gen_string();

        // Write the hidden fields
        $hidden_fields_array_code = "\tprotected static \$hidden_fields_array = [\n";
        $hiddenFields = config( 'durc.hidden_fields' );
        // Hide autoincrement fields by default
        foreach ($fields as $field) {
            if (self::_is_auto_increment($field)) {
                $hiddenField = $field['column_name'];
                $hidden_fields_array_code .= "\t\t'$hiddenField',\n";
            }
        }
        foreach ( $hiddenFields as $hiddenField ) {
            if ( self::has_field( $fields, $hiddenField ) ) {
                $hidden_fields_array_code .= "\t\t'$hiddenField',\n";
            }
        }
        $hidden_fields_array_code .= "\n\t];\n";

		$others = [];
		if(!is_null($belongs_to)){
			foreach($belongs_to as $rel => $belongs_to_data){
				$others[$rel] = $belongs_to_data['type'];
			}
		}

		$with_summary_array_code = "\t\t\$with_summary_array = [];\n";
		foreach($others as $rel => $table_type){
			//why does this fail?
			$with_summary_array_code .= "\t\t\$with_summary_array[] = \"$rel:id,\".\App\\$table_type::getNameField();\n";
			//$with_summary_array_code .= "\t\t\$with_summary_array[] = '$rel';\n";
		}

		$parent_class_name = "$class_name";

		$parent_file_name = "$parent_class_name"."Controller.php";
		$child_file_name = "$class_name"."Controller.php";

		$possible_updated_at_field = self::get_possible_updated_at( $fields );
		$possible_created_at_field = self::get_possible_created_at( $fields );
        $field_update_from_request = "\n";
        foreach($fields as $field_data){
            $this_field = $field_data['column_name'];

            // Don't write explicit variable assignments for timestamps
            if ( $possible_updated_at_field == $this_field ||
                $possible_created_at_field == $this_field ) {
                continue;
            }

            $data_type = $field_data['data_type'];

            // Here we set up the set on the attribute, which contains a call to formatForStorage() in the model,
            // which also sets the default value for non-nullable fields
            // that has a default value defined in the database.
            $field_update_from_request .= "        \$tmp_$class_name"."->$this_field = \$request->$this_field;\n";
        }

        // These lines create the code for saving and redirecting the controller when an error occurs
        $save_model_data = "    \$tmp_$class_name"."->save();\n";
        $save_new_model_redirect = "    return back()->withInput()->with('status', 'There was an error in your data: '.\$e->getMessage());\n";
        $save_update_model_redirect = "    return back()->withInput()->with('errors', \$tmp_".$class_name."->getErrors());\n";

		$parent_class_text = "<?php

namespace App\DURC\Controllers;

use App\\$class_name;
use Illuminate\Http\Request;
use ftrotter\DURC\DURC;
use ftrotter\DURC\DURCController;
use Illuminate\Support\Facades\View;
use ftrotter\DURC\DURCInvalidDataException;

class $class_name"."Controller extends DURCController
{


	public \$view_data = [];

$hidden_fields_array_code

	public function getWithArgumentArray(){
		
$with_summary_array_code
		return(\$with_summary_array);
		
	}


	private function _get_index_list(Request \$request){

		\$return_me = [];

		\$with_argument = \$this->getWithArgumentArray();

		\$these = $class_name::with(\$with_argument)->paginate(100);

        	foreach(\$these->toArray() as \$key => \$value){ //add the contents of the obj to the the view 
			\$return_me[\$key] = \$value;
        	}

		//collapse and format joined data..
		\$return_me_data = [];
        foreach(\$return_me['data'] as \$data_i => \$data_row){
                foreach(\$data_row as \$key => \$value){
                        if(is_array(\$value)){
                                foreach(\$value as \$lowest_key => \$lowest_data){
                                        //then this is a loaded attribute..
                                        //lets move it one level higher...

                                        if ( isset( $class_name::\$field_type_map[\$lowest_key] ) ) {
                                            \$field_type = $class_name::\$field_type_map[ \$lowest_key ];
                                            \$return_me_data[\$data_i][\$key .'_id_DURClabel'] = DURC::formatForDisplay( \$field_type, \$lowest_key, \$lowest_data, true );
                                        } else {
                                            \$return_me_data[\$data_i][\$key .'_id_DURClabel'] = \$lowest_data;
                                        }
                                }
                        }

                        if ( isset( $class_name::\$field_type_map[\$key] ) ) {
                            \$field_type = $class_name::\$field_type_map[ \$key ];
                            \$return_me_data[\$data_i][\$key] = DURC::formatForDisplay( \$field_type, \$key, \$value, true );
                        } else {
                            \$return_me_data[\$data_i][\$key] = \$value;
                        }
                }
        }
        \$return_me['data'] = \$return_me_data;
		
		
        foreach(\$return_me['data'] as \$data_i => \$data_row){
                foreach(\$data_row as \$key => \$value){
                        if(is_array(\$value)){
                                foreach(\$value as \$lowest_key => \$lowest_data){
                                        //then this is a loaded attribute..
                                        //lets move it one level higher...
                                        \$return_me['data'][\$data_i][\$key .'_id_DURClabel'] = \$lowest_data;
                                }
                                unset(\$return_me['data'][\$data_i][\$key]);
                        }
                }
        }


		//helps with logic-less templating...
		if(\$return_me['first_page_url'] == \$return_me['last_page_url']){
			\$return_me['is_need_paging'] = false;
		}else{
			\$return_me['is_need_paging'] = true;
		}

		if(\$return_me['current_page'] == 1){
			\$return_me['first_page_class'] = 'disabled';
			\$return_me['prev_page_class'] = 'disabled';
		}else{
			\$return_me['first_page_class'] = '';
			\$return_me['prev_page_class'] = '';
		}


		if(\$return_me['current_page'] == \$return_me['last_page']){
			\$return_me['next_page_class'] = 'disabled';
			\$return_me['last_page_class'] = 'disabled';
		}else{
			\$return_me['next_page_class'] = '';
			\$return_me['last_page_class'] = '';
		}

		return(\$return_me);
	}

	/**
	*	A simple function that allows fo rthe searching of this object type in the db, 
	*	And returns the results in a select2-json compatible way.
	*	This powers the select2 widgets across the forms...
	*/
   	public function search(Request \$request){

		\$q = \$request->input('q');
		\$page = \$request->input('page');

		//TODO we need to escape this query string to avoid SQL injection.

		//what is the field I should be searching
                \$search_fields = $class_name"."::getSearchFields();

		//sometimes there is an image field that contains the url of an image
		//but this is typically null
		\$img_field = $class_name"."::getImgField();

		\$where_sql = '';
		\$or = '';
		foreach(\$search_fields as \$this_field){
			\$where_sql .= \" \$or \$this_field LIKE '%\$q%'  \";
			\$or = ' OR ';
		}

		\$query = $class_name::whereRaw(\$where_sql);
		            
		\$count = \$query->count();			
		\$these = \$query
		            ->skip(20*(\$page-1))
					->take(20)
					->get();
					
        \$more = \$count > \$page * 20;

		\$return_me['pagination'] = ['more' => \$more];
		\$raw_array = \$these->toArray();

		\$real_array = [];
		foreach(\$raw_array as \$this_row){
			\$tmp = [ 'id' => \$this_row['id']];
			\$tmp_text = '';
			foreach(\$this_row as \$field => \$data){
				if(in_array(\$field,\$search_fields)){
					//then we need to show this text!!
					\$tmp_text .=  \"\$data \";
				}
			}
			\$tmp['text'] = trim(\$tmp_text);

			//show the id of the data at the end of the select..
			\$tmp['text'] .= ' ('.\$this_row['id'].')';;

			if(!is_null(\$img_field)){ //then there is an image for this entry
				\$tmp['img_field'] = \$img_field;
				if(isset(\$this_row[\$img_field])){
					\$tmp['img_url'] = \$this_row[\$img_field];
				}else{	
					\$tmp['img_url'] = null;
				}
			}

			\$real_array[] = \$tmp;
		}


		\$return_me['results'] = \$real_array;

		// you might this helpful for debugging..
		//\$return_me['where'] = \$where_sql;

		return response()->json(\$return_me);

	}

    /**
     * Get a json version of all the objects.. 
     * @param  \App\\$class_name  \$$class_name
     * @return JSON of the object
     */
    public function jsonall(Request \$request){
		return response()->json(\$this->_get_index_list(\$request));
 	}

    /**
     * Display a listing of the resource.
     * @return \Illuminate\Http\Response
     */
    public function index(Request \$request){
        \$main_template_name = \$this->_getMainTemplateName();
    
    
        \$this->view_data = \$this->_get_index_list(\$request);
    
        if(\$request->has('debug')){
            var_export(\$this->view_data);
            exit();
        }
        \$durc_template_results = view('DURC.$class_name.index',\$this->view_data);        
        return view(\$main_template_name,['content' => \$durc_template_results]);
    }


    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  \$request
     * @return \Illuminate\Http\Response
    */ 
    public function store(Request \$request){

        \$myNew$class_name = new $class_name();

        //the games we play to easily auto-generate code..
        \$tmp_$class_name = \$myNew$class_name;
        $field_update_from_request

        try {
        $save_model_data
        \$new_id = \$myNew$class_name"."->id;
        return redirect(\"$URLroot$class_name/\$new_id\")->with('status', 'Data Saved!');
        } catch (DURCInvalidDataException \$e) {
        $save_update_model_redirect
        } catch (\\Exception \$e) {
        $save_new_model_redirect
        }

        
    }//end store function

    /**
     * Display the specified resource.
     * @param  \App\\$$class_name  \$$class_name
     * @return \Illuminate\Http\Response
     */
    public function show(Request \$request, $class_name \$$class_name){
	return(\$this->edit(\$request, \$$class_name));
    }

    /**
     * Get a json version of the given object 
     * @param  \App\\$class_name  \$$class_name
     * @return JSON of the object
     */
    public function jsonone(Request \$request, \$$class_name"."_id){
		\$$class_name = \App\\$class_name::find(\$$class_name"."_id);
		if (\$$class_name === null) {
            return response()->json(\"$class_name with id = {\$$class_name"."_id} Not Found\", 404);
        }
		\$$class_name = \$$class_name"."->fresh_with_relations(); //this is a custom function from DURCModel. you can control what gets autoloaded by modifying the DURC_selfish_with contents on your customized models
		\$return_me_array = \$$class_name"."->toArray();
		\$search_fields = \App\\$class_name::getSearchFields();

        \$tmp_text = '';
        foreach(\$return_me_array as \$field => \$data){
            if(in_array(\$field, \$search_fields)){
                //then we need to show this text!!
                \$tmp_text .=  \"\$data \";
            }
        }
        \$return_me_array['text'] = trim(\$tmp_text);

        //show the id of the data at the end of the select..
        \$return_me_array['text'] .= ' ('.\$return_me_array['id'].')';
		
		
		//lets see if we can calculate a card-img-top for a front end bootstrap card interface
		\$img_uri_field = \App\\$class_name::getImgField();
		if(!is_null(\$img_uri_field)){ //then this object has an image link..
			if(!isset(\$return_me_array['card_img_top'])){ //allow the user to use this as a field without pestering..
				\$return_me_array['card_img_top'] = \$$class_name->\$img_uri_field;
			}
		}

		//lets get a card_body from the DURC mode class!!
		if(!isset(\$return_me_array['card_body'])){ //allow the user to use this as a field without pestering..
			//this is simply the name unless someone has put work into this...
			\$return_me_array['card_body'] = \$$class_name"."->getCardBody();
		}
		
		return response()->json(\$return_me_array);
 	}


    /**
     * Show the form for creating a new resource.
     * @return \Illuminate\Http\Response
     */
    public function create(Request \$request){
        // but really, we are just going to edit a new object..
        \$new_instance = new $class_name();
        return \$this->edit(\$request, \$new_instance); 
    }


    /**
     * Show the form for editing the specified resource.
     * @param  \App\\$class_name  \$$class_name
     * @return \Illuminate\Http\Response
     */
    public function edit(Request \$request, $class_name \$$class_name){

        \$main_template_name = \$this->_getMainTemplateName();
        
        // in case there's flashed input
        \$this->view_data = \$request->old();
    
        //do we have a status message in the session? The view needs it...
        \$this->view_data['session_status'] = session('status',false);
        if(\$this->view_data['session_status']){
            \$this->view_data['has_session_status'] = true;
        }else{
            \$this->view_data['has_session_status'] = false;
        }
        
        // Do we have errors in the session? If so, set local error_messages array, 
        // which contains an array for each field containing error messages
        \$error_messages = [];
        if (\$errors = session('errors', false)) {
            \$error_messages = \$errors->getMessages();
        }
    
        \$this->view_data['csrf_token'] = csrf_token();
        
        foreach ( $class_name::\$field_type_map as \$column_name => \$field_type ) {
            // If this field name is in the configured list of hidden fields, do not display the row.
            \$this->view_data[\"{\$column_name}_row_class\"] = '';
            if ( in_array( \$column_name, self::\$hidden_fields_array ) ) {
                \$this->view_data[\"{\$column_name}_row_class\"] = 'd-none';
            }
            
            // If this field has any errors, set them, otherwise tell view that has_errors is false for this field
            \$this->view_data['errors'][\$column_name]['has_errors'] = false;
            if (isset(\$error_messages[\$column_name])) {
                \$this->view_data['errors'][\$column_name]['has_errors'] = true;
                \$this->view_data['errors'][\$column_name]['messages'] = \$error_messages[\$column_name];
            }
        }
    
        if(\$$class_name"."->exists){	
    
      		//well lets properly eager load this object with a refresh to load all of the related things
      		\$$class_name = \$$class_name"."->fresh_with_relations(); //this is a custom function from DURCModel. you can control what gets autoloaded by modifying the DURC_selfish_with contents on your customized models
    
      		//put the contents into the view...
		//we have to do this even if the object is new, because sometimes the variable is set from a GET or POST request... 
      		foreach(\$$class_name"."->toArray() as \$key => \$value){
                
                	if (array_key_exists(\$key, \$request->old())) {
                    		\$input = \$request->old(\$key);
                	} else {
                    		\$input = \$value;
                	}
            
                	if ( isset( $class_name::\$field_type_map[\$key] ) ) {
                		\$field_type = $class_name::\$field_type_map[ \$key ];
                		\$this->view_data[\$key] = DURC::formatForDisplay( \$field_type, \$key, \$input );
        		} else {
                		\$this->view_data[\$key] = \$input;
        		}
                
       	 		// If this is a nullable field, see whether null checkbox should be checked by default
       	 		if (\$$class_name"."->isFieldNullable(\$key) &&
                		\$input == null) {
                		\$this->view_data[\"{\$key}_checked\"] = \"checked\";
        		}
       		}
    
            	//what is this object called?
            	\$name_field = \$$class_name"."->_getBestName();
            	\$this->view_data['is_new'] = false;
            	\$this->view_data['durc_instance_name'] = \$$class_name"."->\$name_field;

        }else{
		//this has not been saved yet, but we still want to honor GET and POST variables etc. 
        	\$$class_name = new $class_name();
		\$params = \$request->all(); //this will include GET and POST variables, etc
		\$$class_name"."->fill(\$params);  //this will initialize the contents of the object with anything in the GET etc.
		foreach(\$params as \$key => \$value){
			\$this->view_data[\$key] = \$value;
		}
            	\$this->view_data['is_new'] = true;
        }
    
        \$debug = false;
        if(\$debug){
            echo '<pre>';
            var_export(\$this->view_data);
            exit();
        }
        
    
        \$durc_template_results = view('DURC.$class_name.edit',\$this->view_data);        
        return view(\$main_template_name,['content' => \$durc_template_results]);
    }

    /**
     * Update the specified resource in storage.
     * @param  \Illuminate\Http\Request  \$request
     * @param  \App\\$class_name  \$$class_name
     * @return \Illuminate\Http\Response
     */
    public function update(Request \$request, $class_name \$$class_name){

        \$tmp_$class_name = \$$class_name;
        $field_update_from_request
        \$id = \$$class_name"."->id;
        
        try {
        $save_model_data
            return redirect(\"$URLroot$class_name/\$id\")->with('status', 'Data Saved!');
        } catch (DURCInvalidDataException \$e) {
        $save_update_model_redirect
        } catch (\\Exception \$e) {
        $save_new_model_redirect
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param  \App\\$class_name  \$$class_name
     * @return \Illuminate\Http\Response
     */
    public function destroy($class_name \$$class_name){
	    return $class_name::destroy( \${$class_name}->id );  
    }
    
    /**
     * Restore the specified resource from storage.
     * @param  \$id ID of resource
     * @return \Illuminate\Http\Response
     */
    public function restore( \$id )
    {
        \$$class_name = $class_name::withTrashed()->find(\$id)->restore();
        return redirect(\"/DURC/test_soft_delete/\$id\")->with('status', 'Data Restored!');
    }
}
";



		$child_class_text = "<?php

namespace App\Http\Controllers;

use App\\$class_name;
use App\DURC\Controllers\\$class_name"."Controller as DURCParentController;
use Illuminate\Http\Request;

class $class_name"."Controller extends DURCParentController
{
    /**
     * Display a listing of the resource.
     * @return \Illuminate\Http\Response
     */
    public function index(Request \$request){
        // enter your stuff here if you want...

	//anything you put into \$this->view_data will be available in the view...
	//\$this->view_data['how_cool_is_fred'] = 'very'
	//will mean that you can use {{how_cool_is_fred}} etc etc..
	//remember to look in /resources/views/DURC
	//to find the DURC generated views. Once you override those views..
	//DURC will not overwrite them anymore... same thing with this file.. you can change it and it will not
	//be overwritten by subsequent files...

	return(parent::index(\$request));
    }

    /**
     * Show the form for creating a new resource.
     * @return \Illuminate\Http\Response
     */
    public function create(Request \$request){
        // enter your stuff here if you want...
	return(parent::create(\$request));
    }

    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  \$request
     * @return \Illuminate\Http\Response
     */
    public function store(Request \$request){
        // enter your stuff here if you want...
	return(parent::store(\$request));
    }

    /**
     * Display the specified resource.
     * @param  \App\\$$class_name  \$$class_name
     * @return \Illuminate\Http\Response
     */
    public function show(Request \$request, $class_name \$$class_name){
        // enter your stuff here if you want...
	return(parent::show(\$request, \$$class_name));
    }

    /**
     * Show the form for editing the specified resource.
     * @param  \App\\$class_name  \$$class_name
     * @return \Illuminate\Http\Response
     */
    public function edit(Request \$request, $class_name \$$class_name, \$is_new = false){
        // enter your stuff here if you want...
	return(parent::edit(\$request, \$$class_name));
    }

    /**
     * Update the specified resource in storage.
     * @param  \Illuminate\Http\Request  \$request
     * @param  \App\\$class_name  \$$class_name
     * @return \Illuminate\Http\Response
     */
    public function update(Request \$request, $class_name \$$class_name){
        // enter your stuff here if you want...
	return(parent::update(\$request,\$$class_name));
    }

    /**
     * Remove the specified resource from storage.
     * @param  \App\\$class_name  \$$class_name
     * @return \Illuminate\Http\Response
     */
    public function destroy($class_name \$$class_name){
        // enter your stuff here if you want...
	return(parent::destroy(\$$class_name));
    }

}
";

		$app_path = base_path() . '/app/Http/Controllers/'; //we put the editable file in the main app directory where all of the others live..
		$durc_app_path = base_path() . '/app/DURC/Controllers/'; //we put the auto-genertated parent classes in a directory above that..

		if(!is_dir($durc_app_path)){
			if (!mkdir($durc_app_path, 0777, true)) {
    				die("DURC needs to create the $durc_app_path directory... but it could not.. what if it already existed? What then?");
			}
		}

		//get a signed version of the child controller...
		$signed_child_class_code = Signature::sign_phpfile_string($child_class_text);


		$child_file = $app_path.$child_file_name;
		$parent_file = $durc_app_path.$parent_file_name;


                if(file_exists($child_file)){
                        $current_file_contents = file_get_contents($child_file);
                        $has_file_changed = Signature::has_signed_file_changed($current_file_contents);
                        if($has_file_changed){
                                //if a signed file has changed, we never never overwrite it.
                                //it contains manually created code that needs to be protected.
                                //so we do nothing here...
                                echo "Not overwriting $child_file it has been modified\n";
                        }else{
                                //if the file has not been modified, then it is still in its "autogenerated" state.
                                //files in that state are always overridden by newer autogenerated files..
                                file_put_contents($child_file,$signed_child_class_code);
                        }
                }else{
                        //well if the file is missing... lets definately make it!!
                        file_put_contents($child_file,$signed_child_class_code);
                }


		//but we always overwrite the parent class with the auto-generated stuff..
		file_put_contents($parent_file,$parent_class_text);

		return(true);


	}//end generate function











}//end class
