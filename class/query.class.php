<?php

class TQuery extends TObjetStd {
	
	function __construct() {
        global $langs;
         
        parent::set_table(MAIN_DB_PREFIX.'query');
        parent::add_champs('sql_fields,sql_from,sql_where,sql_afterwhere',array('type'=>'text'));
		parent::add_champs('TField,TTable,TOrder,TTitle,TTotal,TLink,THide,TTranslate,TMode,TOperator,TGroup,TFunction,TValue,TJoin,TFilter,TType,TClass',array('type'=>'array'));
		parent::add_champs('expert',array('type'=>'int'));
		
        parent::_init_vars('title,type,xaxis');
        parent::start();    
		
		$this->TGraphiqueType = array(
			'LIST'=>$langs->trans('List')
			,'SIMPLELIST'=>$langs->trans('SimpleList')		
			,'CHART'=>$langs->trans('Columns')
			,'LINE'=>$langs->trans('Lines')
			,'PIE'=>$langs->trans('Pie')
			,'AREA'=>$langs->trans('Area')
		);
		
		$this->TClassName = array(
			'User'=>$langs->trans('User')
			,'Societe'=>$langs->trans('Company')		
			,'Facture'=>$langs->trans('Invoice')		
			,'Propal'=>$langs->trans('Propal')
			,'Commande'=>$langs->trans('Order')
			,'Task'=>$langs->trans('Task')
			,'Project'=>$langs->trans('Project')
		);
		
		$this->show_details = true;
		$this->preview = false;
    }
	
	static function getQueries(&$PDOdb) {
		
		$Tab = array();
		
		$Tab = TRequeteCore::_get_id_by_sql($PDOdb, "SELECT rowid, title FROM ".MAIN_DB_PREFIX."query WHERE 1 ORDER BY title", 'title', 'rowid');
		
		return $Tab;
	}
	
	static function getTables(&$PDOdb) {
		
		$PDOdb->Execute("SHOW TABLES");
		
		$Tab = array();
		while($obj = $PDOdb->Get_line()) {
			
			
			$t = array_values((array)$obj);
			
			$Tab[] = $t[0];
			
		}
		
		return $Tab;
		
	}
	
	static function getFields(&$PDOdb, $table) {
		
		$PDOdb->Execute("DESCRIBE ".$table);
		
		$Tab = array();
		while($obj = $PDOdb->Get_line()) {
			
			$Tab[] = $obj;
			
		}
		
		return $Tab;
		
		
	}
	
	function run(&$PDOdb, $show_details = true, $height=0, $table_element='', $objectid=0, $preview = false) {
		
		$this->show_details = $show_details;
		$this->preview = $preview;
		
		if(!empty($height)) $this->height = $height;
		
		if($this->type == 'CHART') {
			return $this->runChart($PDOdb,'ColumnChart',$table_element,$objectid);
		}
		else if($this->type == 'LINE') {
			return $this->runChart($PDOdb,'LineChart',$table_element,$objectid);
		}else if($this->type == 'PIE') {
			return $this->runChart($PDOdb,'PieChart',$table_element,$objectid);
		}
		else if($this->type == 'AREA') {
			return $this->runChart($PDOdb,'AreaChart',$table_element,$objectid);
		}
		else if($this->type == 'SIMPLELIST' || $this->preview) {
			return load_fiche_titre($this->title).$this->runList($PDOdb,dol_buildpath('/query/tpl/html.simplelist.tbs.html'),$table_element,$objectid);
		}
		else {
			return load_fiche_titre($this->title).$this->runList($PDOdb,'',$table_element,$objectid);	
		}
		
		
	}
	
	private function getField($field) {

		list($t, $fname) = explode('.', $field);
                $fname_concat = empty($fname) ? $t : $t.'_'.$fname;

		return  $fname_concat;
	}

	function getSQL($table_element='',$objectid=0) {
		if($this->expert == 2) {
			return  "SELECT ".$this->sql_fields."
				FROM ".$this->sql_from."
	                        WHERE (".($this->sql_where ? $this->sql_where : 1 ).")
        	                ".$this->sql_afterwhere;
		}
		else if($this->expert == 1) {
			
			if(empty($this->sql_afterwhere) && !empty($this->TGroup)) {
					$this->sql_afterwhere=" GROUP BY ".implode(',', $this->TGroup);	
			}
			
			return  "SELECT ".$this->sql_fields."
				FROM ".$this->sql_from."
	                        WHERE (".($this->sql_where ? $this->sql_where : 1 ).")
        	                ".$this->sql_afterwhere;
		}
		else {
			$this->sql_fields = '';
			foreach($this->TField as $field) {
				
				if(!empty($this->sql_fields))$this->sql_fields.=',';
				
				$fname_concat = $this->getField($field);
				
				if(!empty($this->TFunction[$field])) {
					$this->sql_fields.=strtr($this->TFunction[$field], array('@field@'=> $field)).' as "'.$fname_concat.'"';
				}
				else{
					$this->sql_fields.=$field.' as "'.$fname_concat.'"';
				}
				
			}
			
			$sql="SELECT ".($this->sql_fields ? $this->sql_fields : '*') ."
				FROM ".$this->sql_from."
				WHERE (".($this->sql_where ? $this->sql_where : 1 ).")
				".$this->sql_afterwhere;
			
			if(!empty($table_element) && strpos($sql, $table_element)!==false) {
				$sql.=' AND '.MAIN_DB_PREFIX.$table_element.'.rowid='.$objectid;
			}
	
			if(!empty($this->TGroup)) {
				$sql.=" GROUP BY ".implode(',', $this->TGroup);	
			}
			
			
			if($this->preview && stripos($sql,'LIMIT ') === false) $sql.=" LIMIT 5";
			
			return $sql;	
		}
				
	}

	function getBind() {
		$TBind = array();
		if(!empty($this->TMode)) {
			
			foreach($this->TMode as $f=>$m) {
				
				if(empty($this->TOperator[$f])) continue;
				
				$fBind  = strtr($f, '.', '_');
				$fSearch = strtr($f,'.','_');
				
				if($m == 'function') {
					null;
				}
				else if($m == 'var') {
					
					if($this->TOperator[$f] == '=') {
						null; //impossible case
					}
					else if($this->TOperator[$f] == '<') {
						$TBind[$fBind] = PHP_INT_MAX;
					}
					else if($this->TOperator[$f] == '>') {
						$TBind[$fBind] = ~PHP_INT_MAX;
					}
					else {
						$TBind[$fBind] = '%';	
					}
					
				}
				else if(!empty($this->TValue[$f])) {
					$TBind[$fBind] = $this->TValue[$f];	
				}
				else {
					$TBind[$fBind] = NULL; // mauvaise définition de la valeur à chercher
				}	
				
				
			}
			
		}
		
		return $TBind ;
		
	}
	
	function getOperator() {
		$Tab = array();
		if(!empty($this->TOperator)) {
			
			foreach($this->TOperator as $f=>$v) {
				if($v) {
					$fSearch = strtr($f,'.','_');
					$Tab[$fSearch]= $v;
				}
				
			}
			
		}
		return $Tab;
	}
	
	function getTitle() {
		
		
		$Tab = array();
		if(!empty($this->TTitle)) {
			
			foreach($this->TTitle as $f=>$v) {
				if($v) {
					$fSearch = strtr($f,'.','_');
					$Tab[$fSearch]= $v;
				}
				
			}
			
		}
		return $Tab;
	}
	
	function getEval() {
		
		$Tab=array();
		if(!empty($this->TClass)) {
			
			foreach($this->TClass as $f=>$v) {
				if($v) {
					$fSearch = strtr($f,'.','_');
					$Tab[$fSearch]= 'TQuery::getNomUrl("'.$v.'", (int)@val@)';
				}
				
			}
			
		}
		return $Tab;
	}
	
	static function getNomUrl($type, $id) {
		
		global $langs, $db, $conf;
		
		list($classname, $include) = explode(',', $type);
		
		dol_include_once('/core/class/html.form.class.php');

		if(empty($include)) {
			if($classname == 'User') dol_include_once('/user/class/user.class.php');
			else if($classname == 'Facture') dol_include_once('/compta/facture/class/facture.class.php');
			else if($classname == 'Propal') dol_include_once('/comm/propal/class/propal.class.php');
			else if($classname == 'Commande') dol_include_once('/commande/class/commande.class.php');
			else if($classname == 'Task') dol_include_once('/projet/class/task.class.php');
			else if($classname == 'Projet') dol_include_once('/projet/class/project.class.php');
			else if($classname == 'Societe') dol_include_once('/societe/class/societe.class.php');
			else {
				return $langs->trans('ImpossibleToIncludeClass').' : '.$classname;
			}
		}
		else{
			if(!dol_include_once($include)) {
				return $langs->trans('ImpossibleToIncludeClass').' : '.$include;
			}
		}
		
		if(!class_exists($classname))return $langs->trans('ClassNotIncluded');
		
		$o=new $classname($db);
		$o->fetch($id);
		
		if(method_exists($o, 'getNomUrl')) {
			return $o->getNomUrl(1);
		}
		else {
			return $langs->trans('MethodgetNomUrlNotExist');
		}
		
	}
	
	function getType() {
		
		
		$Tab = array();
		if(!empty($this->TType)) {
			
			foreach($this->TType as $f=>$v) {
				if($v) {
					$fSearch = strtr($f,'.','_');
					$Tab[$fSearch]= $v;
				}
				
			}
			
		}
		return $Tab;
	}
	function getHide() {
		
		$THide = array();
		if(!empty($this->THide)) {

			foreach($this->THide as $f=>$v) {
				if($v) {
					$fSearch = strtr($f,'.','_');
					$THide[]= $fSearch;
				}
				
			}
			
		}
		return $THide;
	}
	function getTranslate() {
		
		$Tab = array();
		if(!empty($this->TTranslate)) {
			
			foreach($this->TTranslate as $f=>$v) {
				$fSearch = strtr($f,'.','_');
				$Tab[$fSearch]=array();
				
				$TPair = str_getcsv($v, ',','"');
				
				foreach($TPair as $pair) {
					
					$pos = strpos($pair,':');
					if($pos!==false) {
						$from = substr($pair, 0, $pos );
						$to = substr($pair, $pos+1 );
						
						$Tab[$fSearch]["$from"] = $to; 	
					}
					
					
				}
				
			}
			
		}
		
		return $Tab;
	}
	function getTotal() {
		
		
		$Tab = array();
		if(!empty($this->TTotal)) {
			
			foreach($this->TTotal as $f=>$v) {
				$fSearch = strtr($f,'.','_');
				$Tab[$fSearch]=$v;
			}
			
		}
		
		return $Tab;
	}
	function getSearch() {
		
		$TSearch = array();
		
		$TTranslate = $this->getTranslate();
		
		if($this->preview) return array(); // mode preview, pas de recherche
		
		if(!empty($this->TMode)) {
			
			foreach($this->TMode as $f=>$m) {
				
				if(($this->expert==0 && empty($this->TOperator[$f])) || $m!='var') continue;
				
				list($tbl, $field) = explode('.', $f);
				if(empty($field)) {
					$field = $tbl; $tbl = '';
				}
				$fSearch = strtr($f,'.','_');
				
				if(!empty($this->TFilter[$f])) {
					$filter = $this->TFilter[$f];
				}
				else if(!empty($TTranslate[$fSearch]) && is_array($TTranslate[$fSearch])) {
					$filter = $TTranslate[$fSearch];
				}
				else {
					$filter = true;
				}
				 
				$TSearch[$fSearch] = array(
					'recherche'=>$filter
					,'table'=>$tbl
					,'field'=>$field
				);
					
				
				
			}
			
		}
		
		return $TSearch;
	}
	function runChart(&$PDOdb, $type = 'LineChart',$table_element='',$objectid=0) {
		global $conf;
		
		$sql=$this->getSQL($table_element,$objectid);
		$TBind = $this->getBind();
		$TSearch = $this->getSearch();
		$THide = $this->getHide();
		$TTitle = $this->getTitle();
		$TTranslate = $this->getTranslate();
		$TOperator = $this->getOperator();
		
		//list($xTable, $xaxis) = explode('.',$this->xaxis);
		$xaxis = strtr($this->xaxis,'.','_');

		$html = '';
		
		$form=new TFormCore();
		$html.= $form->begin_form('auto','formQuery'. $this->getId(),'get');
		
		$html.=  $form->hidden('action', 'run');
		$html.=  $form->hidden('id', GETPOST('id') ? GETPOST('id') : $this->getId());
		
		if($this->show_details) $html.= '<div class="query">'.$sql.'</div>';
		
		$r=new TListviewTBS('chart'.$this->getId());
		$html .= $r->render($PDOdb, $sql,array(
			'type'=>'chart'
			,'chartType'=>$type
			,'translate'=>$TTranslate
			,'liste'=>array(
				'titre'=>$this->title
			)
			,'title'=>$TTitle
			,'xaxis'=>$xaxis
			,'hide'=>$THide
			,'search'=>$TSearch
			,'height'=>$this->height
			,'curveType'=>$conf->global->QUERY_GRAPH_LINESTYLE
			,'pieHole'=>$conf->global->QUERY_GRAPH_PIEHOLE
			,'operator'=>$TOperator
		),$TBind);
		
		if($this->show_details) {
				$html.=  '<div class="query">';
				$Tab=array();
				foreach($r->TBind as $f=>$v) {
					$Tab[] = $f.' : '.$v;
				}
				$html.=  implode(', ', $Tab);
				$html.=  '</div>';
				
		}
			
		$html.=$form->end_form();
		
		return $html;
	}
	function runList(&$PDOdb, $template = '',$table_element='',$objectid=0) {
		
		$html = '';
		
			$sql=$this->getSQL($table_element,$objectid);
			$TBind = $this->getBind();
			$TSearch = $this->getSearch();
			$THide = $this->getHide();
			$TTranslate = $this->getTranslate();
			$TTotal = $this->getTotal();
			$TOperator = $this->getOperator();
			$TType = $this->getType();
			$TEval = $this->getEval();
			
			$form=new TFormCore();
			$html.= $form->begin_form('auto','formQuery'. $this->getId(),'get');
			
			$html.=  $form->hidden('action', 'run');
			$html.=  $form->hidden('id', GETPOST('id') ? GETPOST('id') : $this->getId());
			
			if($this->show_details) $html.= '<div class="query">'.$sql.'</div>';
			
			$TTitle=$this->getTitle();
			
			$r=new TListviewTBS('lRunQuery'. $this->getId(), $template);
//echo 3;
			$html.=  $r->render($PDOdb, $sql,array(
				'link'=>$this->TLink
				,'hide'=>$THide
				,'title'=>$TTitle
				,'liste'=>array(
					'titre'=>''
				)
				,'type'=>$TType
				,'orderBy'=>$this->TOrder
				,'translate'=>$TTranslate
				,'search'=>$TSearch
				,'export'=>array(
					'CSV','TXT'
				)
				,'operator'=>$TOperator
				,'math'=>$TTotal
				,'eval'=>$TEval
			)
			,$TBind);
//echo 4;			
			if($this->show_details) {
				$html.=  '<div class="query">';
				$Tab=array();
				foreach($r->TBind as $f=>$v) {
					$Tab[] = $f.' : '.$v;
				}
				$html.=  implode(', ', $Tab);
				$html.=  '</div>';
				
			}
			
			
			$html.= $form->end_form();
//			var_dump(htmlentities($html));
			return $html;
	}
	
}


dol_include_once('/core/class/menubase.class.php');
	
class TQueryMenu extends TObjetStd {
	function __construct() {
        global $langs;
         
        parent::set_table(MAIN_DB_PREFIX.'query_menu');
        parent::add_champs('fk_menu,fk_query,entity',array('type'=>'int','index'=>true));
		parent::_init_vars('title,perms,mainmenu,leftmenu');
        parent::start();    
		
	}
	
	function save(&$PDOdb) {
		
		global $db,$conf,$user;
		$this->entity = $conf->entity;
		
		
		if($this->fk_menu == 0) {
			
			$menu = new Menubase($db,'all');
			
		    $menu->module='query';
			$menu->type='left';
	        $menu->mainmenu=$menu->fk_mainmenu=$this->mainmenu;
	        $menu->fk_leftmenu=$this->leftmenu;
			
			$menu->leftmenu = 'querymenu'.$this->getId();
			
	        $menu->fk_menu=-1;
			
	        $menu->position=500 + $this->getId();
	        $menu->url='/query/query.php?action=run&id='.$this->fk_query.'&_a='.time();
	        $menu->target='';
	        $menu->titre=$this->title;
	        $menu->langs='query.lang';
	        $menu->perms=$this->perms;
	        $menu->enabled=0;
	        $menu->user=2;
	        
	        $menu->level=0;
			$res = $menu->create($user);
			
			if($res<=0) {
				var_dump($menu);
				exit('Erreur lors de la création du menu');
			} 
			
			$this->fk_menu = $menu->id;
			
			
		}
		else{
			
			$menu = new Menubase($db,'all');
			if($menu->fetch($this->fk_menu)>0) {
				
				$menu->mainmenu=$menu->fk_mainmenu=$this->mainmenu;
	        	$menu->fk_leftmenu=$this->leftmenu;
				$menu->url='/query/query.php?action=run&id='.$this->fk_query.'&_a='.time();
				$menu->leftmenu = 'querymenu'.$this->getId();
				$menu->position=500 + $this->getId();
				$menu->titre=$this->title;
				$menu->enabled=0;
				$menu->level=0;
				$menu->user=2; 
				$menu->update($user);
				
			}
			
		}
		
		parent::save($PDOdb);
	}
	
	function delete(&$PDOdb) {
		
		parent::delete($PDOdb);
		
		if($this->fk_menu > 0) {
			global $db,$conf,$user;
			$menu = new Menubase($db,'all');
			if($menu->fetch($this->fk_menu)>0) {
		
				$menu->delete($user);
			}
		}
	}
	
	static function getMenu(&$PDOdb, $type) {
		global $langs;
		
		if($type == 'main') {
			$Tab = TRequeteCore::_get_id_by_sql($PDOdb, "SELECT DISTINCT mainmenu FROM ".MAIN_DB_PREFIX."menu WHERE 1 ORDER BY mainmenu", 'mainmenu', 'mainmenu');
		}
		else{
			$Tab = TRequeteCore::_get_id_by_sql($PDOdb, "SELECT DISTINCT leftmenu FROM ".MAIN_DB_PREFIX."menu WHERE 1 ORDER BY leftmenu", 'leftmenu', 'leftmenu');
		
		}
		
		
		return $Tab;
	}
	
}
