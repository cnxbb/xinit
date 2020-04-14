<?php  //CODE BY ZMZ
// 读取SQL文件 生成对应的PHP类
ini_set('date.timezone','Asia/Shanghai');

class XField {
    public $Name;
    public $NotNull;
    public $IsInt;
    public $IsFloat;
    public $IsString;
    public $AutoIncrement;
    public $HasDefault;

    public function __construct( $_Name, $_NotNULL = false, $_Type = "string", $_AutoIncrement = true, $_HasDefault = false ) {
        $this->Name = $_Name;
        $this->NotNull = $_NotNULL;
        $this->AutoIncrement = $_AutoIncrement;
        $this->HasDefault = $_HasDefault;

        $this->IsInt = false;
        $this->IsFloat = false;
        $this->IsString = false;
        if( strcmp( $_Type, 'string' ) == 0 ) {
            $this->IsString = true;
        }
        if( strcmp( $_Type, 'int' ) == 0 ) {
            $this->IsInt = true;
        }
        if( strcmp( $_Type, 'float' ) == 0 ) {
            $this->IsFloat = true;
        }

    }
}
//------------------------------------------------------------------------------------

class CodeString {
    private $code;
    public function __construct() {
        $this->code = '';
    }
    //------------------------------------------------------------------------------------
    public function add( $level = 0, $_code, $endl = true ) {
        for( $i=0; $i<$level; $i++ ) {
            $this->code .= "    ";
        }
        $this->code .= $_code;
        if( $endl ) {
            $this->code .= "\n";
        }
    }
    //------------------------------------------------------------------------------------

    public function Save( $fn ) {
        $this->code = str_replace( '##', '$', $this->code );
        return file_put_contents( $fn, $this->code );
    }
    //------------------------------------------------------------------------------------
}
//------------------------------------------------------------------------------------

//写日志
function XLog( $msg ) {
    $file = basename( $_SERVER['SCRIPT_FILENAME'] );
    $logfn = './XLog.txt';

    $logstr = date( 'Y-m-d H:i:s' ) ;
    if( isset( $msg ) ) {
        $logstr .= "Msg: " . var_export( $msg, true ) ;
    }

    $logstr .= "\n\n";
    $f = fopen( $logfn, "w" );
    if( $f !== false ) {
        fwrite( $f, $logstr );
        fclose( $f );
    }
}
//------------------------------------------------------------------------------------

//错误提示
function Err( $errinfo, $bExit = true ) {
    echo "Usage: php sql2php.php [SQL file] [model path] [--overwrite]\n";
    echo "Error:\n";
    if( isset( $errinfo ) ) {
        if( is_string( $errinfo ) ) {
            echo sprintf( "\t%s\n", $errinfo );
        } else if( is_array( $errinfo ) ) {
            foreach( $errinfo as $s ) {
                echo sprintf( "\t%s\n", $s );
            }
        }
    }
    if( $bExit ) {
        exit;
    }
}
//------------------------------------------------------------------------------------

//创建路径
function CreatePath( $path ){
    if( !file_exists( $path ) ) {
        if( CreatePath( dirname( $path ) ) === false ) {
            return false;
        }
        if( mkdir( $path, 0777 ) === false ) {
            return false;
        }
        chmod( $path, 0777 );
    } else {
        if( !is_dir( $path ) ) {
            return false;
        }
    }
    return true;
}
//------------------------------------------------------------------------------------

//生产PHPClass文件
function MakePHPModel( $TableName, $Filders, $FileName ) {
    $c = new CodeString();
    $c->add( 0, '<?php //CODE BY ZMZ' );
    $c->add( 0, sprintf( "// Class For %s\n", $TableName ) );
    $c->add( 0, "require_once 'XInitTableBase.class.php';" );
    $c->add( 0, sprintf( "\nclass %s extends XInitTableBase {", $TableName ) );
    $c->add( 1, 'public function __construct( $_db, $_params ) {' );
    $c->add( 2, 'parent::__construct( $_db, $_params );' );
    $c->add( 1, '}' );
    $c->add( 1, '//------------------------------------------------------------------------------------' );
    $c->add( 0, '');

    //insert
    $c->add( 1, "public function Insert() {" );
    $c->add( 2, "##arr = array();" );
    foreach( $Filders as $field ) {
        $c->add( 2, sprintf( "##%s = ##this->GetParam( '%s', NULL );", $field->Name, $field->Name ) );
        if( $field->NotNull &&
            !$field->AutoIncrement &&
            !$field->HasDefault ) {
            $c->add( 2, sprintf( "if( is_null( ##%s ) ) {", $field->Name ) );
            $c->add( 3, "return ##this->JError('缺少参数');" );
            $c->add( 2, '}' );
        }
        $c->add( 2, sprintf( "if( !is_null( ##%s ) ) {", $field->Name ) );
        if( $field->IsInt ) {
            $c->add( 3, sprintf( "##arr['`%s`'] = intval( %s );", $field->Name, "##" . $field->Name ) );
        } else if( $field->IsFloat ) {
            $c->add( 3, sprintf( "##arr['`%s`'] = trim( %s );", $field->Name, "##" . $field->Name ) );
        } else if( $field->IsString ) {
            $c->add( 3, sprintf( "##arr['`%s`'] = addslashes( %s );", $field->Name, "##" . $field->Name ) );
        }

        $c->add( 2, '}' );
    }
    $c->Add( 2, "##fields = array_keys( ##arr );" );
    $c->Add( 2, "##field_str = implode( ', ', ##fields );" );
    $c->Add( 2, "##val_str = implode( \"', '\", ##arr );" );

    $c->add( 2, sprintf( "##tpl = \"insert into `%s` (%%s) values ('%%s')\";", $TableName ) );
    $c->add( 2, "##sql = sprintf( ##tpl, ##field_str, ##val_str );" );
    //$c->add( 2, 'echo ##sql . "<br>\n";' );
    $c->add( 2, "##ret = ##this->db->run( ##sql );" );
    $c->add( 2, "return ##ret;" );
    $c->add( 1, "}" );
    $c->add( 1, "//------------------------------------------------------------------------------------\n" );

    //update
    $c->add( 1, "public function Edit() {" );
    $c->add( 2, "##arr = array();" );
    $idx = 0;
    foreach( $Filders as $field ) {
        $c->add( 2, sprintf( "##%s = ##this->GetParam( '%s', NULL );", $field->Name, $field->Name ) );
        if( $idx == 0 ) {
            $c->add( 2, sprintf( "if( is_null( ##%s ) ) {", $field->Name ) );
            $c->add( 3, "return ##this->JError( '缺少标示' );" );
            $c->add( 2, '}' );
        } else {
            $c->add( 2, sprintf( "if( !is_null( ##%s ) ) {", $field->Name ) );
            if( $field->IsInt ) {
                $c->add( 3, sprintf( "##arr[] = sprintf( \"`%s` = '%%d'\" , intval( ##%s ) );", $field->Name, $field->Name ) );
            } else if( $field->IsFloat ) {
                $c->add( 3, sprintf( "##arr[] = sprintf( \"`%s` = '%%s'\" , trim( ##%s ) );", $field->Name, $field->Name ) );
            } else if( $field->IsString ) {
                $c->add( 3, sprintf( "##arr[] = sprintf( \"`%s` = '%%s'\" , addslashes( ##%s ) );", $field->Name, $field->Name ) );
            }
            $c->add( 2, '}' );
        }
        $idx ++;
    }
    $c->add( 2, "##vals = implode( ', ', ##arr );" );
    $c->add( 2, sprintf( "##tpl = \"update `%s` set %%s where `%s` = '%%d'\";", $TableName, $Filders[0]->Name ) );
    $c->add( 2, sprintf( "##sql = sprintf( ##tpl, ##vals, intval( ##%s ) );", $Filders[0]->Name ) );
    $c->add( 2, "##ret = ##this->db->run( ##sql );" );
    $c->add( 2, "return ##ret;" );
    $c->add( 1, "}" );
    $c->add( 1, "//------------------------------------------------------------------------------------\n" );

    //delete
    $c->add( 1, "public function Del() {" );
    $c->add( 2, sprintf( "##%s = ##this->GetParam( '%s', NULL );", $Filders[0]->Name, $Filders[0]->Name ) );
    $c->add( 2, sprintf( "if( is_null( ##%s ) ) {", $Filders[0]->Name ) );
    $c->add( 3, "return ##this->JError( '缺少标示' );" );
    $c->add( 2, '}' );
    $c->add( 2, sprintf( "##tpl = \"update %s set m_Delete = 1 where `%s` = '%%d'\";", $TableName, $Filders[0]->Name ) );
    $c->add( 2, sprintf( "##sql = sprintf( ##tpl, intval( ##%s ) );", $Filders[0]->Name ) );
    $c->add( 2, "##ret = ##this->db->run( ##sql );" );
    $c->add( 2, "return ##ret;" );
    $c->add( 1, "}" );
    $c->add( 1, "//------------------------------------------------------------------------------------\n" );

    //remove
    $c->add( 1, "public function Remove() {" );
    $c->add( 2, sprintf( "##%s = ##this->GetParam( '%s', NULL );", $Filders[0]->Name, $Filders[0]->Name ) );
    $c->add( 2, sprintf( "if( is_null( ##%s ) ) {", $Filders[0]->Name ) );
    $c->add( 3, "return ##this->JError( '缺少标示' );" );
    $c->add( 2, '}' );
    $c->add( 2, sprintf( "##tpl = \"delete from %s where `%s` = '%%d'\";", $TableName, $Filders[0]->Name ) );
    $c->add( 2, sprintf( "##sql = sprintf( ##tpl, intval( ##%s ) );", $Filders[0]->Name ) );
    $c->add( 2, "##ret = ##this->db->run( ##sql );" );
    $c->add( 2, "return ##ret;" );
    $c->add( 1, "}" );
    $c->add( 1, "//------------------------------------------------------------------------------------\n" );

    //get
    $c->add( 1, "public function Get() {" );
    $c->add( 2, sprintf( "##%s = ##this->GetParam( '%s', NULL );", $Filders[0]->Name, $Filders[0]->Name ) );
    $c->add( 2, sprintf( "if( is_null( ##%s ) ) {", $Filders[0]->Name ) );
    $c->add( 3, "return ##this->JError( '缺少标示' );" );
    $c->add( 2, '}' );
    $c->add( 2, sprintf( "##tpl = \"select * from %s where m_Delete = 0 and `%s` = '%%d'\";", $TableName, $Filders[0]->Name ) );
    $c->add( 2, sprintf( "##sql = sprintf( ##tpl, intval( ##%s ) );", $Filders[0]->Name ) );
    $c->add( 2, "##ret = ##this->db->run( ##sql );" );
    $c->add( 2, "return ##ret;" );
    $c->add( 1, "}" );
    $c->add( 1, "//------------------------------------------------------------------------------------\n" );

    //list
    $c->add( 1, "public function GetList() {" );
    $c->add( 2, sprintf( "##sql = \"select * from %s where m_Delete = 0\";", $TableName ) );
    $c->add( 2, "##ret = ##this->db->run( ##sql );" );
    $c->add( 2, "return ##ret;" );
    $c->add( 1, "}" );
    $c->add( 1, "//------------------------------------------------------------------------------------\n" );

    //JS用  init_pop 和 make_data 2个函数
    $c->add( 1, "/* JS用  init_pop 和 make_data 2个函数 " );
    $c->add( 1, "function init_pop( obj ) {" );
    foreach( $Filders as $field ) {
        $c->add( 2, sprintf( "\$('#XXXXXX #%s').val('');", $field->Name ) );
    }
    $c->add( 2, "if( typeof( obj ) != 'undefined' ) {" );
    foreach( $Filders as $field ) {
        $c->add( 3, sprintf( "if( typeof( obj.%s) != 'undefined' ) {", $field->Name ) );
        $c->add( 4, sprintf( "\$('#XXXXXX #%s').val( obj.%s);", $field->Name, $field->Name ) );
        $c->add( 3, '}');
    }
    $c->add( 2, "}" );
    $c->add( 1, "}" );

    $c->add( 1, "function make_data( act ) {" );
    $c->add( 2, "var pd = {};" );
    $c->add( 2, "pd.act = act;" );
    foreach( $Filders as $field ) {
        $c->add( 2, sprintf( "pd.%s = \$('#XXXXXX #%s').val();", $field->Name, $field->Name ) );
    }
    $c->add( 2, "return pd;" );
    $c->add( 1, '}' );
    $c->add( 1, '*/' );

    $c->add( 0, "}" );
    $c->add( 0, "//------------------------------------------------------------------------------------\n" );
    $c->Save( $FileName );
}
//------------------------------------------------------------------------------------


//处理一个Create Table语句
function ParseTable ( $str ) {
    global $savepath;
    $str = str_replace( '`', '', $str );
    while( strstr( $str, '  ' ) ) {
        $str = str_replace( '  ', ' ', $str );
    }

    //提取表名称
    $arr = explode( '(', $str );
    $tnameline = $arr[0];
    unset( $arr );
    $arr = explode( " ", $tnameline );
    $TableName = trim( $arr[count($arr) - 2] );
    echo  sprintf( "Table : %s\t", $TableName );

    //提取字段
    $s = strpos( $str, '(' );
    $e = strrpos( $str, ')' );
    $sub = substr( $str, $s+1, $e - $s -1 );

    $arr = array_filter( explode( "\n", $sub ), function( $var ) {
        if( strstr( $var, ',' ) ) {
            return true;
        }
        return false;
    } );
    $Fields = array();
    foreach( $arr as $fitem ) {
        $fitem = trim( $fitem );
        $arr_field = explode( ' ', $fitem );
        $FieldName = $arr_field[0];
        $notnull = stristr( $fitem, " not null" ) ? TRUE : FALSE;
        $autoincrement = stristr( $fitem, " auto_increment" ) ? TRUE : FALSE;
        $hasdef = stristr( $fitem, " default " ) ? TRUE : FALSE;
        $ftype = 'string';
        if( stristr( $fitem, ' int ' ) ) {
            $ftype = 'int';
        } else if( stristr( $fitem, ' tinyint(' ) ) {
            $ftype = 'int';
        } else if( stristr( $fitem, ' float ' ) ) {
            $ftype = 'float';
        } else if( stristr( $fitem, ' double ') ) {
            $ftype = 'float';
        }
        $Fields[] = new XField( $FieldName, $notnull, $ftype, $autoincrement, $hasdef );

    }
    XLog( $Fields );
    MakePHPModel( $TableName, $Fields, $savepath . '/' . $TableName . '.class.php' );
    echo sprintf( "Create: %s.class.php OK.\n", $TableName );
    usleep(100000);

}
//------------------------------------------------------------------------------------

if( !isset( $argv[1] ) ) {
    Err( "SQL file not found" );
}

$dbfile = trim( $argv[1] );
if( $dbfile == '' ) {
    Err( "SQL file not found" );
}

if( !file_exists( $dbfile ) ) {
    Err( "SQL file not found" );
}

$savepath = '.';
if( isset( $argv[2] ) && strlen( trim( $argv[2] ) ) > 0 ) {
    $savepath = trim( $argv[2] );
}

if( CreatePath( $savepath ) == false ) {
    Err( 'Create Path Error' );
}

//读取SQL
$str = file_get_contents( $dbfile );
$str = preg_replace( "/(\/\*.*\*\/)/", "", $str ); //过滤 /* */ 注释

//提取Create Table 行
$arr = array_filter( explode( ';', $str ), function( $var ) {
    if( stripos( trim( $var ), 'create table' ) === 0 ) {
        return true;
    }
    return false;
} );

foreach( $arr as $line ) {
    ParseTable( $line );
    //exit;
}
echo "Completed.\n";
exit;
