#include "inc.h"

double snmp_get(char *snmp_host, char *snmp_comm, int ver, char *snmp_oid, int who){

  void *sessp = NULL;
  struct snmp_session session;
  struct snmp_pdu *pdu = NULL;
  struct snmp_pdu *response = NULL;
  oid anOID[MAX_OID_LEN];
  size_t anOID_len = MAX_OID_LEN;
  struct variable_list *vars = NULL;
  int status=0;
  char result_string[BUFSIZE];
  unsigned long long int result;
  double dresult;

  //snmp query
  snmp_sess_init(&session);
  if(ver=2) session.version = SNMP_VERSION_2c;
  else session.version = SNMP_VERSION_1;
  session.peername = snmp_host;
  session.community = snmp_comm;
  session.community_len = strlen(session.community);
  sessp = snmp_sess_open(&session);
  anOID_len = MAX_OID_LEN;
  pdu = snmp_pdu_create(SNMP_MSG_GET);
  read_objid(snmp_oid, anOID, &anOID_len);

  snmp_add_null_var(pdu, anOID, anOID_len);
  printf("[%i] SNMP: query done\n",who);
  if(sessp != NULL){
    status = snmp_sess_synch_response(sessp, pdu, &response);
    if (status == STAT_TIMEOUT) printf("[%i] SNMP: Timeout (%s@%s).\n",who, session.peername, snmp_oid);
    else if (status != STAT_SUCCESS) printf("[%i] SNMP: Unsuccessuful (%s@%s) (%d).\n", who, session.peername, snmp_oid, status);
    else if (status == STAT_SUCCESS && response->errstat != SNMP_ERR_NOERROR) printf("[%i] SNMP: Error (%s@%s) %s\n",who, session.peername,snmp_oid, snmp_errstring(response->errstat));
    if (status == STAT_SUCCESS && response->errstat == SNMP_ERR_NOERROR){
      vars = response->variables;
      snprintf(result_string, BUFSIZE, anOID, anOID_len, vars);
      printf("[%i] SNMP: %s\n", who, result_string);
      //if counter is 64bit
      if(vars->type == ASN_COUNTER64){
        result = vars->val.counter64->high;
        result = result << 32;
        result = result + vars->val.counter64->low;
      } else {
        //if counter is 32bit
        if(vars->type == ASN_COUNTER) result = (unsigned long) *(vars->val.integer);
        else {
          //if counter is not counter
          printf("[%i] SNMP: This is not counter!\n", who);
        }
      }
    }
  } else printf("[%i] SNMP: (%s) Bad descriptor.\n",who, session.peername);

  //free
  if (response) snmp_free_pdu(response);
  if (sessp != NULL) snmp_sess_close(sessp);
  return result;
}

snmpasync_get(){

};
