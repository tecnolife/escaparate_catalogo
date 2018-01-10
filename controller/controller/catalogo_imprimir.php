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
    public $resultados;

    public function __construct($name = __CLASS__, $title = 'catálogo', $folder = 'ventas')
    {
        parent::__construct($name, $title, $folder, FALSE, FALSE);
    }

    protected function private_core()
 {
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
        
        if ($logo){
            $size = getimagesize($logo);
            $width = $size[0] * 15 / $size[1];
            $pdf->SetHeaderData('../../'.$logo, $width, 'Catálogo', $familia->descripcion.' '.$fabricante->nombre.' '.$almacen->nombre.' '.$tarifa->nombre);
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
                    $descripcion = substr($value->descripcion(), 0, 12) . '...';
                } else {
                    $descripcion = $value->descripcion();
                }
                $html .= $descripcion . ' ' . $value->referencia . '<br/>';
                $html .= $this->show_precio($value->pvp, FALSE, TRUE, FS_NF0_ART) . '</td>';
                
                $celda++;
                
            } elseif (filter_input(INPUT_GET, 'sinimagen') == "True") {
                $html .= '<td><img style="height: 177px;" src="plugins/escaparate_catalogo/sinimagen.jpg"><br/>';
                if (strlen($value->descripcion()) > 15) {
                    $descripcion = substr($value->descripcion(), 0, 12) . '...';
                } else {
                    $descripcion = $value->descripcion();
                }
                $html .= $descripcion . ' - ' . $value->referencia . '<br/>';
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

        $this->b_constock = isset($_REQUEST['b_constock']);
        $this->b_bloqueados = isset($_REQUEST['b_bloqueados']);
        $this->b_publicos = isset($_REQUEST['b_publicos']);

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
}
