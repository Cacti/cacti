#include <pthread.h>
#include <signal.h>
#include <time.h>
#include <sys/time.h>
#ifdef NETSNMP
#include <net-snmp-config.h>
#include <net-snmp-includes.h>
#endif
#ifdef UCDSNMP
#include <ucd-snmp-config.h>
#include <ucd-snmp-includes.h>
#endif
#include <mysql.h>
#include <rrd.h>
#include "global.h"
