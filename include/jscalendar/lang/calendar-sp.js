// ** I18N
Calendar._DN = new Array
("Domingo",
 "Lunes",
 "Martes",
 "Miercoles",
 "Jueves",
 "Viernes",
 "Sabado",
 "Domingo");
Calendar._MN = new Array
("Enero",
 "Febrero",
 "Marzo",
 "Abril",
 "Mayo",
 "Junio",
 "Julio",
 "Agosto",
 "Septiembre",
 "Octubre",
 "Noviembre",
 "Diciembre");

// tooltips
Calendar._TT = {};
Calendar._TT["INFO"] = "Información del Calendario";

Calendar._TT["ABOUT"] =
"DHTML Date/Time Selector\n" +
"(c) dynarch.com 2002-2003\n" + // don't translate this this ;-)
"Nuevas versiones en: http://dynarch.com/mishoo/calendar.epl\n" +
"Distribuida bajo licencia GNU LGPL.  Para detalles vea http://gnu.org/licenses/lgpl.html ." +
"\n\n" +
"Selección de Fechas:\n" +
"- Use  \xab, \xbb para seleccionar el año\n" +
"- Use " + String.fromCharCode(0x2039) + ", " + String.fromCharCode(0x203a) + " para seleccionar el mes\n" +
"- Mantenga presionado el botón del ratón en cualquiera de las opciones superiores para un acceso rapido .";
Calendar._TT["ABOUT_TIME"] = "\n\n" +
"Selección del Reloj:\n" +
"- Seleccione la hora para cambiar el reloj\n" +
"- o presione  Shift-click para disminuirlo\n" +
"- o presione click y arrastre del ratón para una selección rapida.";

Calendar._TT["TOGGLE"] = "Primer dia de la semana";
Calendar._TT["PREV_YEAR"] = "Año anterior (Presione para menu)";
Calendar._TT["PREV_MONTH"] = "Mes Anterior (Presione para menu)";
Calendar._TT["GO_TODAY"] = "Ir a Hoy";
Calendar._TT["NEXT_MONTH"] = "Mes Siguiente (Presione para menu)";
Calendar._TT["NEXT_YEAR"] = "Año Siguiente (Presione para menu)";
Calendar._TT["SEL_DATE"] = "Seleccione fecha";
Calendar._TT["DRAG_TO_MOVE"] = "Arrastre y mueva";
Calendar._TT["PART_TODAY"] = " (Hoy)";
Calendar._TT["MON_FIRST"] = "Lunes Primero";
Calendar._TT["SUN_FIRST"] = "Domingo Primero";
Calendar._TT["CLOSE"] = "Cerrar";
Calendar._TT["TODAY"] = "Hoy";

// date formats
Calendar._TT["DEF_DATE_FORMAT"] = "dd-mm-yy";
Calendar._TT["TT_DATE_FORMAT"] = "%A, %e de %B de %Y";

Calendar._TT["WK"] = "Smn";
