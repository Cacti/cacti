#define BUFSIZE 512

typedef struct target_struct{
  int target_id;
  unsigned long long result;
  int local_data_id;
  int action;
  char command[256];
  char management_ip[16];
  char snmp_community[100];
  int snmp_version;
  char snmp_username[50];
  char snmp_password[50];
  char rrd_name[19];
  char rrd_path[255];
  char arg1[255];
  char arg2[255];
  char arg3[255];
  struct target_struct *next;
  struct target_struct *prev;
  struct target_struct *head;
}target_t;

typedef struct rrd_struct{
  char rrd_name[19];
  char rrd_path[255];
  unsigned long long int result;
}rrd_t;

typedef struct conf_struct{
  char sqluser[80];
  char sqlpw[80];
  char sqlhost[80];
  char sqldb[80];
  int interval;
} conf_t;

void *poller();
unsigned long long int snmp_get(char *snmp_host, char *snmp_comm, int ver, char *snmp_oid, int who);

