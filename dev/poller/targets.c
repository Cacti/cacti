#include "inc.h"

int get_targets(){
  char query[256];
  extern target_t *targets;
  extern conf_t conf;
  target_t *temp;
  target_t *temp2;
  MYSQL mysql;
  MYSQL_RES *result;
  MYSQL_ROW row;
  mysql_init(&mysql);
  if (!mysql_real_connect(&mysql, conf.sqlhost, conf.sqluser, conf.sqlpw, conf.sqldb, 0, NULL, 0)){
    fprintf(stderr, "%s\n", mysql_error(&mysql));
    exit(1);
  }
  sprintf(query, "select action,command,management_ip,snmp_community, \
    snmp_version, snmp_username, snmp_password, rrd_name, rrd_path, \
    arg1, arg2, arg3, local_data_id from data_input_data_cache order \
    by local_data_id");
  if (mysql_query(&mysql, query)) fprintf(stderr, "Error in query\n");
  if ((result = mysql_store_result(&mysql)) == NULL){
    fprintf(stderr, "Error retrieving data\n");
    exit(1);
  }
  mysql_close(&mysql);
  free(targets);
  targets=NULL;
  while ((row = mysql_fetch_row(result))) {
    temp = (target_t *) malloc(sizeof(target_t));
    
    temp->action = atoi(row[0]);
    sprintf(temp->command, row[1]);
    sprintf(temp->management_ip, row[2]);
    sprintf(temp->snmp_community, row[3]);
    temp->snmp_version = atoi(row[4]);
    //not used at the moment
    sprintf(temp->snmp_username, row[5]);
    //not used at the moment
    sprintf(temp->snmp_password, row[6]);
    sprintf(temp->rrd_name, row[7]);
    sprintf(temp->rrd_path, row[8]);
    sprintf(temp->arg1, row[9]);
    sprintf(temp->arg2, row[10]);
    sprintf(temp->arg3, row[11]);
    temp->local_data_id = atoi(row[12]);

    temp->prev=NULL;
    temp->next=NULL;
    if(targets == NULL) targets = temp;
    else{
      for(temp2 = targets; temp2->next !=NULL; temp2 = temp2->next);
      temp->prev = temp2;
      temp2->next = temp;
    }
  }
  temp=NULL;
  free(temp);
  temp2=NULL;
  free(temp2);
  return mysql_num_rows(result);;
}
