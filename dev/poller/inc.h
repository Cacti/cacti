#include <time.h>
#include <sys/time.h>
#include <mysql.h>
#include <string.h>


//For Net-SNMP (ver5)
#ifdef NETSNMP
#include <net-snmp-config.h>
#include <net-snmp-includes.h>
#endif

//For Ucd-SNMP (ver4?)
#ifdef UCDSNMP
#include <ucd-snmp-config.h>
#include <ucd-snmp-includes.h>
#endif

//For internal rrd
#ifdef RRD
#include <rrd.h>
#endif

//Lot of usefull stuff...
#include "global.h"
