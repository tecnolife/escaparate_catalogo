<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'plugins/facturacion_base/extras/fbase_controller.php';
require_once 'plugins/tcpdf/tcpdf.php';
require_model('amortizacion.php');
require_model('fabricante.php');
require_model('almacen.php');
require_model('tarifa.php');

/**
 * Esta clase agrupa los procedimientos de imprimir/enviar albaranes de proveedor
 * e imprimir facturas de proveedor.
 */



class catalogo_imprimir extends fbase_controller
{
    public $almacenes;
    public $b_bloqueados;
    public $b_codalmacen;
    public $b_codfabricante;
    public $b_codfamilia;
    public $b_codtarifa;
    public $b_constock;
    public $b_orden;
    public $b_publicos;
    public $b_subfamilias;
    public $b_url;
    public $familia;
    public $fabricante;
    public $impuesto;
    public $mostrar_tab_tarifas;
    public $offset;
    public $resultados;
    public $total_resultados;
    public $tarifa;
    public $transferencia_stock;

    public function __construct($name = __CLASS__, $title = 'catálogo', $folder = 'ventas')
    {
        parent::__construct($name, $title, $folder, FALSE, FALSE);
    }

    protected function private_core()
 {
        $this->tarifa = new tarifa;
        $this->ini_filters();
        $this->search_articulos();

        // create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('FacturaScripts');
        $pdf->SetTitle('Catalogo');
        $pdf->SetSubject('TCPDF Tutorial');
        $pdf->SetKeywords('TCPDF, PDF, example, test, guide');

// set default header data
        
        if (file_exists(FS_MYDOCS . 'images/logo.png')) {
            $logo = 'images/logo.png';
        } else if (file_exists(FS_MYDOCS . 'images/logo.jpg')) {
            $logo = 'images/logo.jpg';
        } else {
            $logo = false;
        }
        
        if (isset($_REQUEST['b_codfamilia'])) {
            $fam = new familia();
            $familia = $fam->get($_REQUEST['b_codfamilia']);
        }
        if (isset($_REQUEST['b_codfabricante'])) {
            $fab = new fabricante();
            $fabricante = $fab->get($_REQUEST['b_codfabricante']);
        }
        if (isset($_REQUEST['b_codalmacen'])) {
            $alm = new almacen();
            $almacen = $alm->get($_REQUEST['b_codalmacen']);
        }
        if (isset($_REQUEST['b_codtarifa'])) {
            $tar = new tarifa();
            $tarifa = $tar->get($_REQUEST['b_codtarifa']);
        }
        
        if ($familia != 0) {
            $titulo = $familia->descripcion.' ';
        }
        if ($fabricante != 0) {
            $titulo = $titulo.$fabricante->nombre.' ';
        }
        if ($almacen != 0) {
            $titulo = $titulo.$almacen->nombre.' ';
        }
        if ($tarifa != 0) {
            $titulo = $titulo.$tarifa->nombre.' ';
        }
        
        if ($logo){
            $size = getimagesize($logo);
            $width = $size[0] * 15 / $size[1];
            $pdf->SetHeaderData('../../'.$logo, $width, 'Catálogo', $titulo);
        } else {
            $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, $this->empresa->nombrecorto, 'Catálogo '.$_REQUEST['b_codfamilia'].' '.$_REQUEST['b_codfabricante'].' '.$_REQUEST['b_codalmacen'].' '.$_REQUEST['codtarifa']);
        }
        
// set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
        if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
            require_once(dirname(__FILE__) . '/lang/eng.php');
            $pdf->setLanguageArray($l);
        }

// ---------------------------------------------------------
// add a page
        $pdf->AddPage();


        $pdf->SetFont('helvetica', '', 11);

// -----------------------------------------------------------------------------

        $html = '<table cellspacing="0" cellpadding="1" border="1"><tr>';

        $celda = 0;
        foreach ($this->resultados as $value) {
            $img = $value->imagen_url();
            
            if ($img) {
                $html .= '<td><img style="height: 177px;" src="' . $img . '"><br/>';
                if (strlen($value->descripcion()) > 15) {
                    $descripcion = mb_substr($value->descripcion(), 0, 12, 'UTF-8') . '...';
                } else {
                    $descripcion = $value->descripcion();
                }
                $html .= $descripcion . ' ' . $value->referencia . '<br/>';
                $html .= $this->show_precio($value->pvp, FALSE, TRUE, FS_NF0_ART) . '</td>';
                
                $celda++;
                
            } elseif (filter_input(INPUT_GET, 'sinimagen') == "True") {
                $html .= '<td><img style="height: 177px;" src="plugins/escaparate_catalogo/sinimagen.jpg"><br/>';
                if (strlen($value->descripcion()) > 15) {
                    $descripcion = mb_substr($value->descripcion(), 0, 12, 'UTF-8') . '...';
                } else {
                    $descripcion = $value->descripcion();
                }
                
                
                $html .= $descripcion . ' ' . $value->referencia . '<br/>';
                $html .= $this->show_precio($value->pvp, FALSE, TRUE, FS_NF0_ART) . '</td>';
                
                $celda++;
            }
            
            if ($celda == 3) {
                $html .= '</tr><tr>';
                $celda = 0;
            }
        }

        if ($celda < 3) {
            $html .= '</tr><tr>';
        }

        $html .= '</table>';

        $pdf->writeHTML($html, false, false, true, false, '');

        $pdf->Output('catalogo.pdf', 'I');
    }

    private function ini_filters()
    {
        $this->offset = 0;
        if (isset($_REQUEST['offset'])) {
            $this->offset = intval($_REQUEST['offset']);
        }

        $this->b_codalmacen = '';
        if (isset($_REQUEST['b_codalmacen'])) {
            $this->b_codalmacen = $_REQUEST['b_codalmacen'];
        }

        $this->b_codfamilia = '';
        $this->b_subfamilias = FALSE;
        if (isset($_REQUEST['b_codfamilia'])) {
            $this->b_codfamilia = $_REQUEST['b_codfamilia'];
            if ($_REQUEST['b_codfamilia']) {
                $this->b_subfamilias = isset($_REQUEST['b_subfamilias']);
            }
        }

        $this->b_codfabricante = '';
        if (isset($_REQUEST['b_codfabricante'])) {
            $this->b_codfabricante = $_REQUEST['b_codfabricante'];
        }

        //Modificado por Tecnolife
        //$this->b_constock = isset($_REQUEST['b_constock']);
        if ($_REQUEST['b_constock'] == 1) {
            $this->b_constock = 1;
        } 
        //$this->b_bloqueados = isset($_REQUEST['b_bloqueados']);
        if ($_REQUEST['b_bloqueados'] == 1) {
            $this->b_bloqueados = 1;
        } 
        //$this->b_publicos = isset($_REQUEST['b_publicos']);
        if ($_REQUEST['b_publicos'] == 1) {
            $this->b_publicos = 1;
        } 

        $this->b_codtarifa = '';
        if (isset($_REQUEST['b_codtarifa'])) {
            $this->b_codtarifa = ($_REQUEST['b_codtarifa']);
            setcookie('b_codtarifa', $this->b_codtarifa, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_COOKIE['b_codtarifa'])) {
            $this->b_codtarifa = $_COOKIE['b_codtarifa'];
        }

        $this->b_orden = 'refmin';
        if (isset($_REQUEST['b_orden'])) {
            $this->b_orden = $_REQUEST['b_orden'];
            setcookie('ventas_articulos_orden', $this->b_orden, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_COOKIE['ventas_articulos_orden'])) {
            $this->b_orden = $_COOKIE['ventas_articulos_orden'];
        }

        $this->b_url = $this->url() . "&query=" . $this->query
            . "&b_codfabricante=" . $this->b_codfabricante
            . "&b_codalmacen=" . $this->b_codalmacen
            . "&b_codfamilia=" . $this->b_codfamilia
            . "&b_codtarifa=" . $this->b_codtarifa;

        if ($this->b_subfamilias) {
            $this->b_url .= '&b_subfamilias=TRUE';
        }

        if ($this->b_constock) {
            $this->b_url .= '&b_constock=TRUE';
        }

        if ($this->b_bloqueados) {
            $this->b_url .= '&b_bloqueados=TRUE';
        }

        if ($this->b_publicos) {
            $this->b_url .= '&b_publicos=TRUE';
        }
    }
    
    private function search_articulos()
    {        
        $this->resultados = array();
        $this->num_resultados = 0;
        $sql = ' FROM articulos ';
        $where = ' WHERE ';

        if ($this->query) {
            $query = $this->empresa->no_html(mb_strtolower($this->query, 'UTF8'));
            $sql .= $where;
            if (is_numeric($query)) {
                /// ¿La búsqueda son números?
                $sql .= "(referencia = " . $this->empresa->var2str($query)
                    . " OR referencia LIKE '%" . $query . "%'"
                    . " OR partnumber LIKE '%" . $query . "%'"
                    . " OR equivalencia LIKE '%" . $query . "%'"
                    . " OR descripcion LIKE '%" . $query . "%'"
                    . " OR codbarras = " . $this->empresa->var2str($query) . ")";
            } else {
                /// ¿La búsqueda son varias palabras?
                $palabras = explode(' ', $query);
                if (count($palabras) > 1) {
                    $sql .= "(lower(referencia) = " . $this->empresa->var2str($query)
                        . " OR lower(referencia) LIKE '%" . $query . "%'"
                        . " OR lower(partnumber) LIKE '%" . $query . "%'"
                        . " OR lower(equivalencia) LIKE '%" . $query . "%'"
                        . " OR (";

                    foreach ($palabras as $i => $pal) {
                        if ($i == 0) {
                            $sql .= "lower(descripcion) LIKE '%" . $pal . "%'";
                        } else {
                            $sql .= " AND lower(descripcion) LIKE '%" . $pal . "%'";
                        }
                    }

                    $sql .= "))";
                } else {
                    $sql .= "(lower(referencia) = " . $this->empresa->var2str($query)
                        . " OR lower(referencia) LIKE '%" . $query . "%'"
                        . " OR lower(partnumber) LIKE '%" . $query . "%'"
                        . " OR lower(equivalencia) LIKE '%" . $query . "%'"
                        . " OR lower(codbarras) = " . $this->empresa->var2str($query)
                        . " OR lower(descripcion) LIKE '%" . $query . "%')";
                }
            }
            $where = ' AND ';
        }

        if ($this->b_codfamilia) {
            if ($this->b_subfamilias) {
                $sql .= $where . "codfamilia IN (";
                $coma = '';
                foreach ($this->get_subfamilias($this->b_codfamilia) as $fam) {
                    $sql .= $coma . $this->empresa->var2str($fam);
                    $coma = ',';
                }
                $sql .= ")";
            } else {
                $sql .= $where . "codfamilia = " . $this->empresa->var2str($this->b_codfamilia);
            }
            $where = ' AND ';
        }

        if ($this->b_codfabricante) {
            $sql .= $where . "codfabricante = " . $this->empresa->var2str($this->b_codfabricante);
            $where = ' AND ';
        }

        if ($this->b_constock) {
            if ($this->b_codalmacen == '') {
                $sql .= $where . "stockfis > 0";
            } else {
                $sql .= $where . "referencia IN (SELECT referencia FROM stocks WHERE cantidad > 0"
                    . " AND codalmacen = " . $this->empresa->var2str($this->b_codalmacen) . ')';
            }
            $where = ' AND ';
        }

        if ($this->b_publicos) {
            $sql .= $where . "publico = TRUE";
            $where = ' AND ';
        }

        if ($this->b_bloqueados) {
            $sql .= $where . "bloqueado = TRUE";
            $where = ' AND ';
        } else {
            $sql .= $where . "bloqueado = FALSE";
            $where = ' AND ';
        }

        $order = 'referencia DESC';
        switch ($this->b_orden) {
            case 'stockmin':
                $order = 'stockfis ASC';
                break;

            case 'stockmax':
                $order = 'stockfis DESC';
                break;

            case 'refmax':
                if (strtolower(FS_DB_TYPE) == 'postgresql') {
                    $order = 'referencia DESC';
                } else {
                    $order = 'lower(referencia) DESC';
                }
                break;

            case 'descmin':
                $order = 'descripcion ASC';
                break;

            case 'descmax':
                $order = 'descripcion DESC';
                break;

            case 'preciomin':
                $order = 'pvp ASC';
                break;

            case 'preciomax':
                $order = 'pvp DESC';
                break;

            default:
            case 'refmin':
                if (strtolower(FS_DB_TYPE) == 'postgresql') {
                    $order = 'referencia ASC';
                } else {
                    $order = 'lower(referencia) ASC';
                }
                break;
        }

        $data = $this->db->select("SELECT COUNT(referencia) as total" . $sql);
        if ($data) {
            $this->total_resultados = intval($data[0]['total']);

            /// ¿Descargar o mostrar en pantalla?
            if (isset($_GET['download'])) {
                $this->download_resultados($sql, $order);
            } else {
                $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $order, 1000, $this->offset);
                if ($data2) {
                    foreach ($data2 as $i) {
                        $this->resultados[] = new articulo($i);
                    }

                    if ($this->b_codalmacen != '') {
                        /// obtenemos el stock correcto
                        foreach ($this->resultados as $i => $value) {
                            $this->resultados[$i]->stockfis = 0;
                            foreach ($value->get_stock() as $s) {
                                if ($s->codalmacen == $this->b_codalmacen) {
                                    $this->resultados[$i]->stockfis = $s->cantidad;
                                }
                            }
                        }
                    }

                    if ($this->b_codtarifa != '') {
                        /// aplicamos la tarifa
                        $tarifa = $this->tarifa->get($this->b_codtarifa);
                        if ($tarifa) {
                            $tarifa->set_precios($this->resultados);

                            /// si la tarifa añade descuento, lo aplicamos al precio
                            foreach ($this->resultados as $i => $value) {
                                $this->resultados[$i]->pvp -= $value->pvp * $value->dtopor / 100;
                            }
                        }
                    }
                }
            }
        }
    }
    
    private function download_resultados($sql, $order)
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"articulos.csv\"");
        echo "referencia;codfamilia;codfabricante;descripcion;pvp;iva;codbarras;stock;coste\n";

        $offset2 = 0;
        $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $order, 1000, $offset2);
        while ($data2) {
            $resultados = array();
            foreach ($data2 as $i) {
                $resultados[] = new articulo($i);
            }

            if ($this->b_codalmacen != '') {
                /// obtenemos el stock correcto
                foreach ($resultados as $i => $value) {
                    $resultados[$i]->stockfis = 0;
                    foreach ($value->get_stock() as $s) {
                        if ($s->codalmacen == $this->b_codalmacen) {
                            $resultados[$i]->stockfis = $s->cantidad;
                        }
                    }
                }
            }

            if ($this->b_codtarifa != '') {
                /// aplicamos la tarifa
                $tarifa = $this->tarifa->get($this->b_codtarifa);
                if ($tarifa) {
                    $tarifa->set_precios($resultados);

                    /// si la tarifa añade descuento, lo aplicamos al precio
                    foreach ($resultados as $i => $value) {
                        $resultados[$i]->pvp -= $value->pvp * $value->dtopor / 100;
                    }
                }
            }

            /**
             * libreoffice y excel toman el punto y 3 decimales como millares,
             * así que si el usuario ha elegido 3 decimales, mejor usamos 4.
             */
            $nf0 = FS_NF0_ART;
            if ($nf0 == 3) {
                $nf0 = 4;
            }

            /// escribimos los datos de los artículos
            foreach ($resultados as $art) {
                echo $art->referencia . ';';
                echo $art->codfamilia . ';';
                echo $art->codfabricante . ';';
                echo fs_fix_html(preg_replace('~[\r\n]+~', ' ', $art->descripcion)) . ';';
                echo number_format($art->pvp, $nf0, FS_NF1, '') . ';';
                echo number_format($art->get_iva(), 2, FS_NF1, '') . ';';
                echo trim($art->codbarras) . ';';
                echo number_format($art->stockfis, 2, FS_NF1, '') . ';';
                echo number_format($art->preciocoste(), $nf0, FS_NF1, '') . "\n";

                $offset2++;
            }

            $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $order, 1000, $offset2);
        }
    }
    
}
