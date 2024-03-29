<?php
include_once '../../includes/constants.php';
$propietario = new propietario();

if (!isset($_GET['id'])) {    
    $propietario->esPropietarioLogueado();
    $session = $_SESSION;
}
$accion = isset($_GET['accion']) ? $_GET['accion'] : "listar";


switch ($accion) {
    
    // <editor-fold defaultstate="collapsed" desc="cancelacion">
    case "cancelacion":
        $titulo = $_GET['id'] . ".pdf";
        $content = 'Content-type: application/pdf';
        $url = ROOT . "cancelacion.gastos/" . $_GET['id'] . ".pdf";
        header('Content-Disposition: attachment; filename="' . $titulo . '"');
        header($content);
        readfile($url);
        break;
    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="ver">
    case "ver":
        $propiedad = new propiedades();
        $inmuebles = new inmueble();
        $pagos = new pago();

        $propiedades = $propiedad->propiedadesPropietario($_SESSION['usuario']['cedula']);
        $cuenta = Array();

        if ($propiedades['suceed'] == true) {

            foreach ($propiedades['data'] as $propiedad) {

                $inmueble = $inmuebles->ver($propiedad['id_inmueble']);
                $pago = $pagos->listarPagosProcesados($propiedad['id_inmueble'], $propiedad['apto'], 5);

                if ($pago['suceed'] == true) {
                    
                    for ($index = 0; $index < count($pago['data']); $index++) {
                        if (RECIBO_GENERAL==1) {
                            $filename = "../../cancelacion.gastos/" . $pago['data'][$index]['n_recibo'] . ".pdf";
                            $pago['data'][$index]['recibo'] = file_exists($filename);
                        } else {
                            $filename = "../../cancelacion.gastos/" . $pago['data'][$index]['id_factura'] . ".pdf";
                            $pago['data'][$index]['recibo'] = file_exists($filename);
                        }
                    }
                    
                    $cuenta[] = Array("inmueble" => $inmueble['data'][0],
                        "propiedades" => $propiedad,
                        "cuentas" => $pago['data']);
                }
            }
        }
        
        echo $twig->render('enlinea/pago/cancelacion.html.twig', array("session" => $session,
            "cuentas" => $cuenta));


        break; // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="guardar">
    case "guardar":
        $pago = new pago();
        $data = $_POST;
        if (count($data) > 0) {
            unset($data['registrar']);
            $data['fecha']=date("Y-m-d H:i:00 ", time());
            $exito = $pago->registrarPago($data);
        } else {
            header("location:" . URL_SISTEMA . "/pago/registrar");
            return;
        }
        
        echo json_encode($exito);
        //echo $twig->render('enlinea/pago/formulario.html.twig', array("session" => $session,
        //    "resultado" => $exito,
        //    "accion" => "registrar"
        //));
        break;
    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="registrar">
        case "registrar":
        case "listar":
        default :
        $propiedad = new propiedades();
        $facturas = new factura();
        $inmuebles = new inmueble();
        $resultado = Array();
        if ($accion == 'guardar') {
            $resultado = $exito;
        }
        
        $propiedades = $propiedad->propiedadesPropietario($_SESSION['usuario']['cedula']);

        $cuenta = Array();
        
//        $bitacora->insertar(Array(
//            "id_sesion"=>$session['id_sesion'],
//            "id_accion"=> 8,
//            "descripcion"=>'Inicio del proceso',
//        ));
        
        if ($propiedades['suceed'] == true) {

            foreach ($propiedades['data'] as $propiedad) {

            $inmueble = $inmuebles->ver($propiedad['id_inmueble']);
            $factura = $facturas->estadoDeCuenta($propiedad['id_inmueble'], $propiedad['apto']);
            
                if ($factura['suceed'] == true) {
                    
                    //if ($propiedad['meses_pendiente'] < MESES_COBRANZA) {
                    
                    for ($index = 0; $index < count($factura['data']); $index++) {
                        $filename = "../avisos/".$factura['data'][$index]['numero_factura'].".pdf";
                        $factura['data'][$index]['aviso'] = file_exists($filename);
                        $factura['data'][$index]['pagado'] = pago::facturaPendientePorProcesar($factura['data'][$index]['periodo'], $factura['data'][$index]['id_inmueble'], $factura['data'][$index]['apto']);

                    }
                    //}
                    
                    $cuenta[] = Array(
                            "inmueble"      => $inmueble['data'][0],
                            "propiedades"   => $propiedad,
                            "cuentas"       => $factura['data'],
                            "resultado"     => $resultado
                            );
                }
            }
        }
        echo $twig->render('enlinea/pago/formulario.html.twig', array("session" => $session,
        "cuentas" => $cuenta,
        "accion" => $accion,
        "usuario"=>$session['usuario'],
        "propiedades"=>$propiedades['data']
        ));
        break; 
// </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="listaPagoDetalle">
    case "listaPagosDetalle":
        $pagos = new pago();
        $pago_detalle = $pagos->detalleTodosPagosPendientes();
        if ($pago_detalle['suceed'] && count($pago_detalle['data']) > 0) {
            echo "id_pago,id_inmueble,id_apto,monto,id_factura<br>";
            foreach ($pago_detalle['data'] as $value) {
                echo $value['id_pago'] . ",";
                echo $value['id_inmueble'] . ",";
                echo $value['id_apto'] . ",";
                echo $value['monto'] * 100 . ",";
                echo $value['id_factura'];
                echo "<br>";
            }
        }
        break;  
// </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="listaPagosMaestros">
    case "listaPagosMaestros":
        $pagos = new pago();
        $pagos_maestro = $pagos->listarPagosPendientes();

        if ($pagos_maestro['suceed'] && count($pagos_maestro['data']) > 0) {
            echo "id,fecha,tipo_pago,numero_documento,fecha_documento,monto,banco_origen,";
            echo "banco_destino,numero_cuenta,estatus,email,enviado,telefono<br>";
            foreach ($pagos_maestro['data'] as $pago) {
                echo $pago['id'] . ",";
                echo Misc::date_format($pago['fecha']) . ",";
                echo strtoupper($pago['tipo_pago']) . ","; 
               echo $pago["numero_documento"] . ",";
                echo Misc::date_format($pago["fecha_documento"]) . ",";
                echo $pago["monto"] * 100 . ",";
                echo $pago["banco_origen"] . ",";
                echo $pago["banco_destino"] . ",";
                echo str_replace("-", "", "#".$pago["numero_cuenta"]) . ",";
                echo strtoupper($pago["estatus"]) . ",";
                echo $pago["email"] . ",";
                echo $pago["enviado"] . ",";
                echo $pago["telefono"];
                echo "<br>";
            }
        }
        break; 
// </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="listaPagosPendientes">
    case "listarPagosPendientes":
        $pagos = new pago();
        $pagos_maestro = $pagos->listarPagosPendientes();

        if ($pagos_maestro['suceed'] && count($pagos_maestro['data']) > 0) {

            foreach ($pagos_maestro['data'] as $pago) {

                $pago_detalle = $pagos->detallePagoPendiente($pago['id']);

                if ($pago_detalle['suceed'] && count($pago_detalle['data'] > 0)) {
                    $enviado = $pago["enviado"] == 0 ? "False" : "True";
                    echo "|" . $pago['id'] . "|";
                    echo Misc::date_format($pago['fecha']) . "|";
                    echo strtoupper($pago['tipo_pago']) . "|";
                    echo $pago["numero_documento"] . "|";
                    echo Misc::date_format($pago["fecha_documento"]) . "|";
                    echo Misc::number_format($pago["monto"]) . "|";
                    echo $pago["banco_origen"] . "|";
                    echo $pago["banco_destino"] . "|";
                    echo $pago["numero_cuenta"] . "|";
                    echo strtoupper($pago["estatus"]) . "|";
                    echo $pago["email"] . "|";
                    echo $enviado . "|";
                    echo $pago["telefono"] . "|";
                    // --
                    foreach ($pago_detalle['data'] as $value) {
                        echo $value['id_inmueble'] . "|";
                        echo $value['id_apto'] . "|";
                        echo Misc::number_format($value['monto']) . "|";
                        echo $value['id_factura'] . "|";
                        echo $value['periodo'] . "|";
                    }
                    echo "<br>";
                
                }
            
            }
        

        } else {
            echo "0";
        }


        break; // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="confirmación de pago">
    case "confirmar":


        $pago = new pago();
        $id = $_GET['id'];
        $estatus = $_GET['estatus'];
        $recibo = isset($_GET['recibo'])? $_GET['recibo']:null;
        
        $r = $pago->procesarPago($id, $estatus,$recibo);
        echo $r;
        break; // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="actualizar factura">
    case "actualizar_factura":

        if (isset($_GET['inmueble']) && isset($_GET['apto']) && isset($_GET['id']) && isset($_GET['monto'])) {

            $pagos = new pago();
            $r = $pagos->actualizarFactura($_GET['inmueble'], $_GET['apto'], $_GET['id'], $_GET['monto']);


            if (!$r['stats']['error'] == '') {
                echo $r['stats']['error'];
            }

        
        }
        break; 
    // </editor-fold>
}