// ** I18N
Calendar._DN = new Array
("Søndag",
 "Mandag",
 "Tirsdag",
 "Onsdag",
 "Torsdag",
 "Fredag",
 "Lørdag",
 "Søndag");
Calendar._MN = new Array
("January",
 "Februar",
 "Marts",
 "April",
 "Maj",
 "Juni",
 "Juli",
 "August",
 "September",
 "Oktober",
 "November",
 "December");

// tooltips
Calendar._TT = {};
Calendar._TT["INFO"] = "Om Kalenderen";

Calendar._TT["ABOUT"] =
"DHTML Date/Time Selector\n" +
"(c) dynarch.com 2002-2003\n" + // don't translate this this ;-)
"For den seneste version besøg: http://dynarch.com/mishoo/calendar.epl\n" +
"Distribueret under GNU LGPL.  Se http://gnu.org/licenses/lgpl.html for detajler." +
"\n\n" +
"Valg af dato:\n" +
"- Brug \xab, \xbb knapperne for at vælge år\n" +
"- Brug " + String.fromCharCode(0x2039) + ", " + String.fromCharCode(0x203a) + " knapperne for at vælge måned\n" +
"- Hold knappen på musen nede på knapperne ovenfor for hurtigere valg.";
Calendar._TT["ABOUT_TIME"] = "\n\n" +
"Valg af tid:\n" +
"- Klik på en vilkårlig del for større værdi\n" +
"- eller Shift-klik for for mindre værdi\n" +
"- eller klik og træk for hurtigere valg.";

Calendar._TT["TOGGLE"] = "Skift første ugedag";
Calendar._TT["PREV_YEAR"] = "Ét år tilbage (hold for menu)";
Calendar._TT["PREV_MONTH"] = "Én måned tilbage (hold for menu)";
Calendar._TT["GO_TODAY"] = "Gå til i dag";
Calendar._TT["NEXT_MONTH"] = "Én måned frem (hold for menu)";
Calendar._TT["NEXT_YEAR"] = "Ét år frem (hold for menu)";
Calendar._TT["SEL_DATE"] = "Vælg dag";
Calendar._TT["DRAG_TO_MOVE"] = "Træk vinduet";
Calendar._TT["PART_TODAY"] = " (i dag)";
Calendar._TT["MON_FIRST"] = "Vis mandag først";
Calendar._TT["SUN_FIRST"] = "Vis søndag først";
Calendar._TT["CLOSE"] = "Luk vinduet";
Calendar._TT["TODAY"] = "I dag";

// date formats
Calendar._TT["DEF_DATE_FORMAT"] = "dd-mm-yy";
Calendar._TT["TT_DATE_FORMAT"] = "%d. %b, %Y";

Calendar._TT["WK"] = "wk";
