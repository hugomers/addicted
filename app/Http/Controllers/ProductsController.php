<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function index(){
        $articulos=[];
        $proced = "SELECT CODART  FROM F_ART";
        $exec = $this->conn->prepare($proced);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($fil as $row){
            $articulos[]="'".$row['CODART']."'";
        }
        return response()->json($articulos,200);
    }

    public function pairingProducts(Request $request){
        $fil = $request->pro;
        $delete = "DELETE FROM F_ART WHERE CODART NOT IN (".implode(",",$fil).")";
        $exec = $this->conn->prepare($delete);
        $exec -> execute();
        $delete = "DELETE FROM F_LTA WHERE ARTLTA NOT IN (".implode(",",$fil).")";
        $exec = $this->conn->prepare($delete);
        $exec -> execute();
        $delete = "DELETE FROM F_STO WHERE ARTSTO NOT IN (".implode(",",$fil).")";
        $exec = $this->conn->prepare($delete);
        $exec -> execute();

        $proced = "SELECT CODART FROM F_ART";
        $exec = $this->conn->prepare($proced);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);
        $colsTab = array_keys($fil[0]);//llaves de el arreglo 
        foreach($fil as $row){
            foreach($colsTab as $col){ $row[$col] = utf8_encode($row[$col]); }
            $codigo[]="'".$row['CODART']."'";
        }

        $alm = "SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($alm);
        $exec -> execute();
        $rowalm=$exec->fetchall(\PDO::FETCH_ASSOC);

        $cedis = env('ACCESS_CEDIS');
        $url = $cedis."/Diller/public/api/products/missing";
        $ch = curl_init($url);//inicio de curl
        $data = json_encode(["products" => $codigo]);//se codifica el arreglo de los proveedores
        //inicio de opciones de curl
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//se envia por metodo post
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        //fin de opciones e curl
        $exec = curl_exec($ch);//se executa el curl
        $exc = json_decode($exec,true);//se decodifican los datos decodificados
        curl_close($ch);//cirre de curl
            if($exc == null){
                return response()->json("LOS ARTICULOS ESTAN BIEN");
            }else{
                $arch = $exc['articulos'];
                foreach($arch as $pro){
                    $res[] = "articulo ".$pro['CODART']." insertado con exito";
                    $ins[] = $pro['CODART'];
                    $rows = array_values($pro);
                    $insert = "INSERT INTO F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,FALART,FUMART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,MPTART,UEQART) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                    $exec = $this->conn->prepare($insert);
                    $exec -> execute($rows);
                }

                $prec = $exc['precios'];
                foreach($prec as $prices){
                    $pri = array_values($prices);
                    $insert = "INSERT INTO F_LTA (TARLTA, ARTLTA , MARLTA , PRELTA) VALUES (?,?,?,?)";
                    $exec = $this->conn->prepare($insert);
                    $exec -> execute($pri);
                }
            
                $query = "INSERT INTO F_STO(ARTSTO, ALMSTO, MINSTO, MAXSTO, ACTSTO, DISSTO) VALUES(?,?,?,?,?,?)";
                $exec = $this->conn->prepare($query);
                foreach($rowalm as $alma){
                    $almacen = $alma['CODALM'];
                    foreach($ins as $artins){    
                        try{
                        $exec->execute([$artins, $almacen, 0, 0, 0, 0]);
                        }catch (\PDOException $e){ die($e->getMessage());}
                    }
                }

                return response()->json($res);
            }
    }

    public function replaceProducts(Request $request){
        $factusol=[];
        $products = $request->product;
        foreach($products as $product){
            $original = "'".$product['original']."'";
            $replace = "'".$product['replace']."'";
            try{
            $upda = "UPDATE F_LFA SET ARTLFA = $replace WHERE ARTLFA = $original";
            $exec = $this->conn->prepare($upda);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en facturas por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en facturas";}
            $updsto = "UPDATE F_LFR SET ARTLFR = $replace WHERE ARTLFR = $original";
            $exec = $this->conn->prepare($updsto);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en facturas recibidas por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en facturas recibidas";}
            $updlta = "UPDATE F_LEN SET ARTLEN = $replace WHERE ARTLEN = $original";
            $exec = $this->conn->prepare($updlta);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en entradas por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en entradas";}
            $updltr = "UPDATE F_LTR SET ARTLTR = $replace WHERE ARTLTR = $original";
            $exec = $this->conn->prepare($updltr);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en traspasos por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en traspasos";}
            $updcin = "UPDATE F_LFB SET ARTLFB = $replace WHERE ARTLFB = $original";
            $exec = $this->conn->prepare($updcin);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en abonos por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en abonos";}
            $upddev = "UPDATE F_LFD SET ARTLFD = $replace WHERE ARTLFD = $original";
            $exec = $this->conn->prepare($upddev);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." articulos remplazado en devoluciones por ".$product['replace'];}else{$factusol[]=$product['original']." error en remplazar en devoluciones";}
            $deleteart = "DELETE FROM F_ART WHERE CODART = $original";
            $exec = $this->conn->prepare($deleteart);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." eliminado en Articulos";}else{$factusol[]=$product['original']." error sl eliminar en Articulos";}
            $deletetar = "DELETE FROM F_LTA WHERE ARTLTA = $original";
            $exec = $this->conn->prepare($deletetar);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." eliminado en Precios";}else{$factusol[]=$product['original']." error sl eliminar en Precios";}
            $deletesto = "DELETE FROM F_STO WHERE ARTSTO = $original";
            $exec = $this->conn->prepare($deletesto);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." eliminado en Stock";}else{$factusol[]=$product['original']." error sl eliminar en Stock";}
            $deleteean = "DELETE FROM F_EAN WHERE ARTEAN = $original";
            $exec = $this->conn->prepare($deleteean);
            $exec -> execute();
            if($exec){$factusol[]=$product['original']." eliminado en Familiarizados";}else{$factusol[]=$product['original']." error sl eliminar en Familiarizados";}
            }catch (\PDOException $e){ die($e->getMessage());}
        }
        return response()->json($factusol);

      
    }
    
    public function highProducts(Request $request){
        $insertados=[];
        $actualizados=[];
        $fail=[
            "categoria"=>[],
            "codigo_barras"=>[],
            "codigo_corto"=>[], 
        ];
        $almacenes ="SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($almacenes);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);

        $tari ="SELECT CODTAR FROM F_TAR";
        $exec = $this->conn->prepare($tari);
        $exec -> execute();
        $filtar=$exec->fetchall(\PDO::FETCH_ASSOC);

        $articulos= $request->product;
        
        foreach($articulos as $art){
            $codigo = trim($art["CODIGO"]);
            $deslarga = trim($art["DESCRIPCION"]);
            $desgen = trim(substr($art["DESCRIPCION"],0,50));
            $deset = trim(substr($art["DESCRIPCION"],0,30));
            $destic = trim(substr($art["DESCRIPCION"],0,20));
            $famart = trim($art["FAMILIA"]);
            $cat = trim($art["CATEGORIA"]);
            $date_format = date("d/m/Y");
            // $barcode = trim($art["CB"]);
            if(isset($art["CB"])){$barcode = trim($art["CB"]);}else{$barcode = null;}
            // $cost = $art["COSTO"];
            if(isset($art["COSTO"])){$cost = $art["COSTO"];}else{$cost = 0;}
            // $medidas = trim($art["MEDIDAS NAV"]);
            if(isset($art["MEDIDAS NAV"])){$medidas = trim($art["MEDIDAS NAV"]);}else{$medidas = null;}
            // $luces = trim($art["#LUCES"]);
            if(isset($art["#LUCES"])){$luces = trim($art["#LUCES"]);}else{$luces = null;}
            $PXC = trim($art["PXC"]);
            $refart = trim($art["REFERENCIA"]);
            $cp3art = trim($art["UNIDA MED COMPRA"]);

            $codbar = $barcode == null ? "'"."'" : $barcode;

            $articulo  = [              
                $codigo,
                $codbar,
                $famart,
                $desgen,
                $deset,
                $destic,
                $deslarga,
                $art["PXC"],
                $art["CODIGO CORTO"],
                $art["PROVEEDOR"],
                $refart,
                $art["FABRICANTE"],
                $cost,
                $date_format,
                $date_format,
                $art["PXC"],
                1,
                1,
                1,
                $cat,
                $luces,
                $cp3art,
                $art["PRO RES"],
                $medidas,
                0,
                "Peso"
            ];



            $caty = DB::table('product_categories as PC')->join('product_categories as PF', 'PF.id', '=','PC.root')->where('PC.alias', $cat)->where('PF.alias', $famart)->value('PC.id');
            if($caty){
                $sqlart = "SELECT CODART, EANART FROM F_ART WHERE CODART = ?";
                $exec = $this->conn->prepare($sqlart);
                $exec -> execute([$codigo]);
                $arti=$exec->fetch(\PDO::FETCH_ASSOC);
     
                if($arti){
                    $update = "UPDATE F_ART SET FAMART = "."'".$famart."'"." , CP1ART = "."'".$cat."'"."  , FUMART = "."'".$date_format."'".", EANART = ".$codbar.", PCOART = ".$cost.", UPPART = ".$PXC." , EQUART = ".$PXC.", REFART = "."'".$refart."'"."  , CP3ART = "."'".$cp3art."'"."  WHERE CODART = ? "; 
                    $exec = $this->conn->prepare($update);
                    $exec -> execute([$codigo]);
                    $actualizados[]="Se actualizo el modelo  ".$codigo." con codigo de barras ".$barcode;
                }else{
                    if($barcode != null){
                    $codigob = "SELECT CODART, EANART FROM F_ART WHERE EANART = "."'".$barcode."'";
                    $exec = $this->conn->prepare($codigob);
                    $exec -> execute();
                    $barras=$exec->fetch(\PDO::FETCH_ASSOC);
                    if($barras){$fail['codigo_barras'][]="El codigo de barras ".$barcode." esta otorgado a el articulo ".$barras['CODART']." no se pueden duplicar";}
                    }else{
                        
                        $codigoc = "SELECT CODART, CCOART FROM F_ART WHERE CCOART = ".$art["CODIGO CORTO"];
                        $exec = $this->conn->prepare($codigoc);
                        $exec -> execute();
                        $corto=$exec->fetch(\PDO::FETCH_ASSOC);
                    
                        if($corto){$fail['codigo_corto'][]="El codigo corto ".$art["CODIGO CORTO"]." esta otorgado al articulo ".$corto['CODART']." no se pueden duplicar";
                        }else{
                            $insert = "INSERT INTO F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,FALART,FUMART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,MPTART,UEQART) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                            $exec = $this->conn->prepare($insert);
                            $exec -> execute($articulo);
                            foreach($fil as $row){
                                $alm=$row['CODALM'];
                                $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                                $exec = $this->conn->prepare($insertsto);
                                $exec -> execute([$codigo,$alm,0,0,0,0]);
                            }
                            foreach($filtar as $tar){
                                $price =$tar['CODTAR'];
                                $insertlta = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES (?,?,?,?) ";
                                $exec = $this->conn->prepare($insertlta);
                                $exec -> execute([$price,$codigo,0,0]);
                            }
                            $insertados[]="Se inserto el codigo ".$codigo."con exito";
                        }
                    }
                } 
            }else{$fail['categoria'][]="no existe la categoria ".$cat." de la familia ".$famart." de el producto ".$codigo;}    
        }
        $res = [
            "insertados"=>$insertados,
            "acutalizados"=>$actualizados,
            "fail"=>$fail
        ];
        return response()->json($res);

    }

    
}
