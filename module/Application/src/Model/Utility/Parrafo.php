<?php
namespace Application\Model\Utility;


class Parrafo{
	/*
	 * $text: texto a dividir
	 * $tam: tama�o aproximado de fuente en glyphs
	 */
	public static function getLines($text,$tam,$ancho_pagina_glyphs = 9500){
		$pos_texto = 0;
		$linea = "";
		$long_linea = 0;
		$ultimo_espacio = 0;
		$inicio_linea = 0;
		//$ancho_pagina_glyphs = 9500;
		$lineas = array();
		$tam *= 10;
		$j = 0;
		for ($i=0; $i<strlen($text); $i++) {
			if ($long_linea + $tam < $ancho_pagina_glyphs) {
				$long_linea += $tam;
				$linea .= $text[$i];
				if ($text[$i] === " ") {
					$ultimo_espacio = $i-$inicio_linea;
				}
			}else{
				$resto = "";
				if ($ultimo_espacio + $inicio_linea <= $i && $ultimo_espacio != 0) {
					$resto = substr($linea, $ultimo_espacio, strlen($linea)-$ultimo_espacio);
					$linea = substr($linea, 0, $ultimo_espacio);
				}
				$lineas[$j] = trim($linea);
				$j++;
				$long_linea = (strlen($resto)+1) * $tam;
				$linea = $resto.$text[$i];
				$inicio_linea = $i-strlen($resto);
				$ultimo_espacio = 0;
			}
		}
		$lineas[$j] = trim($linea);
		return $lineas;
	}
}
