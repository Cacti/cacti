// Author: Hartwig Weinkauf h_weinkauf@gmx.de
// �erarbeitet und fehlende Texte hinzugefgt von Gerhard Neinert (gerhard at neinert punkt de)
// Feel free to use / redistribute under the GNU LGPL.
// ** I18N

// short day names
Calendar._SDN = new Array
("So",
 "Mo",
 "Di",
 "Mi",
 "Do",
 "Fr",
 "Sa",
 "So");

// full day names
Calendar._DN = new Array
("Sonntag",
 "Montag",
 "Dienstag",
 "Mittwoch",
 "Donnerstag",
 "Freitag",
 "Samstag",
 "Sonntag");

// short day names only use 2 letters instead of 3
Calendar._SDN_len = 2;

// full month names
Calendar._MN = new Array
("Januar",
 "Februar",
 "M\u00e4rz",
 "April",
 "Mai",
 "Juni",
 "Juli",
 "August",
 "September",
 "Oktober",
 "November",
 "Dezember");

// short month names
Calendar._SMN = new Array
("Jan",
 "Feb",
 "M\u00e4r",
 "Apr",
 "Mai",
 "Jun",
 "Jul",
 "Aug",
 "Sep",
 "Okt",
 "Nov",
 "Dez");

// tooltips
Calendar._TT = {};

Calendar._TT["ABOUT"] =
"DHTML Datum/Zeit Selector\n" +
"(c) dynarch.com 2002-2003\n" + // don't translate this this ;-)
"Donwload neueste Version: http://dynarch.com/mishoo/calendar.epl\n" +
"Distributed under GNU LGPL.  See http://gnu.org/licenses/lgpl.html for details." +
"\n\n" +
"Datumsauswahl:\n" +
"- Jahr ausw\u00e4hlen mit \xab und \xbb\n" +
"- Monat ausw\u00e4hlen mit " + String.fromCharCode(0x2039) + " und " + String.fromCharCode(0x203a) + "\n" +
"- Fr Auswahl aus Liste Maustaste gedr\u00fcckt halten.";

Calendar._TT["ABOUT_TIME"] = "\n\n" +
"Zeit w\u00e4hlen:\n" +
"- Stunde/Minute weiter mit Mausklick\n" +
"- Stunde/Minute zurck mit Shift-Mausklick\n" +
"- oder f\u00fcr schnellere Auswahl nach links oder rechts ziehen.";


Calendar._TT["TOGGLE"] = "Ersten Tag der Woche w\u00e4hlen";
Calendar._TT["PREV_YEAR"] = "Jahr zur\u00fcck (halten -> Auswahlmen\u00fc)";
Calendar._TT["PREV_MONTH"] = "Monat zur\u00fcck (halten -> Auswahlmen\u00fc)";
Calendar._TT["GO_TODAY"] = "Gehe zum heutigen Datum";
Calendar._TT["NEXT_MONTH"] = "Monat vor (halten -> Auswahlmen\u00fc)";
Calendar._TT["NEXT_YEAR"] = "Jahr vor (halten -> Auswahlmen\u00fc)";
Calendar._TT["SEL_DATE"] = "Datum ausw\u00e4hlen";
Calendar._TT["DRAG_TO_MOVE"] = "Klicken und halten um zu verschieben";
Calendar._TT["PART_TODAY"] = " (heute)";
Calendar._TT["MON_FIRST"] = "Wochenanzeige mit Montag beginnen";
Calendar._TT["SUN_FIRST"] = "Wochenanzeige mit Sonntag beginnen";
Calendar._TT["CLOSE"] = "Schlie\u00dfen";
Calendar._TT["TODAY"] = "Heute";

// date formats
Calendar._TT["DEF_DATE_FORMAT"] = "dd-mm-y";
Calendar._TT["TT_DATE_FORMAT"] = "Datum ausw\u00e4hlen";

Calendar._TT["WK"] = "KW";
