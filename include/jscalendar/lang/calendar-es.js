// ** I18N

// Calendar EN language
// Author: Mihai Bazon, <mishoo@infoiasi.ro>
// Encoding: any
// Distributed under the same terms as the calendar itself.

// For translators: please use UTF-8 if possible.  We strongly believe that
// Unicode is the answer to a real internationalized world.  Also please
// include your contact information in the header, as can be seen above.

// full day names
Calendar._DN = new Array
("Domingo",
 "Lunes",
 "Martes",
 "Miircoles",
 "Jueves",
 "Viernes",
 "Sabado",
 "Domingo");

// Please note that the following array of short day names (and the same goes
// for short month names, _SMN) isn't absolutely necessary.  We give it here
// for exemplification on how one can customize the short day names, but if
// they are simply the first N letters of the full name you can simply say:
//
//   Calendar._SDN_len = N; // short day name length
//   Calendar._SMN_len = N; // short month name length
//
// If N = 3 then this is not needed either since we assume a value of 3 if not
// present, to be compatible with translation files that were written before
// this feature.

// short day names
Calendar._SDN = new Array
("Dom",
 "Lun",
 "Mar",
 "Mii",
 "Jue",
 "Vie",
 "Sab",
 "Dom");

// full month names
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

// short month names
Calendar._SMN = new Array
("Ene",
 "Feb",
 "Mar",
 "Abr",
 "May",
 "Jun",
 "Jul",
 "Ago",
 "Sep",
 "Oct",
 "Nov",
 "Dic");

// tooltips
Calendar._TT = {};
Calendar._TT["INFO"] = "Acerca del calendario";

Calendar._TT["ABOUT"] =
"Selector DHTML de Fecha/Hora\n" +
"(c) dynarch.com 2002-2003\n" + // don't translate this this ;-)
"Para conseguir la zltima versisn visite: http://dynarch.com/mishoo/calendar.epl\n" +
"Distribuido bajo licencia GNU LGPL. Visite http://gnu.org/licenses/lgpl.html para mas detalles." +
"\n\n" +
"Seleccisn de fecha:\n" +
"- Use los botones \xab, \xbb para seleccionar el aqo\n" +
"- Use los botones " + String.fromCharCode(0x2039) + ", " + String.fromCharCode(0x203a) + " para seleccionar el mes\n" +
"- Mantenga pulsado el ratsn en cualquiera de estos botones para una seleccisn rapida.";
Calendar._TT["ABOUT_TIME"] = "\n\n" +
"Seleccisn de hora:\n" +
"- Pulse en cualquiera de las partes de la hora para incrementarla\n" +
"- s pulse las mayzsculas mientras hace clic para decrementarla\n" +
"- s haga clic y arrastre el ratsn para una seleccisn mas rapida.";

Calendar._TT["PREV_YEAR"] = "Aqo anterior (mantener para menu)";
Calendar._TT["PREV_MONTH"] = "Mes anterior (mantener para menu)";
Calendar._TT["GO_TODAY"] = "Ir a hoy";
Calendar._TT["NEXT_MONTH"] = "Mes siguiente (mantener para menu)";
Calendar._TT["NEXT_YEAR"] = "Aqo siguiente (mantener para menu)";
Calendar._TT["SEL_DATE"] = "Seleccionar fecha";
Calendar._TT["DRAG_TO_MOVE"] = "Arrastrar para mover";
Calendar._TT["PART_TODAY"] = " (hoy)";
Calendar._TT["MON_FIRST"] = "Mostrar lunes primero";
Calendar._TT["SUN_FIRST"] = "Mostrar domingo primero";
Calendar._TT["CLOSE"] = "Cerrar";
Calendar._TT["TODAY"] = "Hoy";
Calendar._TT["TIME_PART"] = "(Mayzscula-)Clic o arrastre para cambiar valor";

// date formats
Calendar._TT["DEF_DATE_FORMAT"] = "%d/%m/%Y";
Calendar._TT["TT_DATE_FORMAT"] = "%A, %e de %B de %Y";

Calendar._TT["WK"] = "sem";
