#include "inc.h"

int update_rrd(rrd_t *rrd_targets, int rrd_target_count){
  int i;
  FILE *rrdtool_stdin;
  char rrdcmd[512];
  char **rrdargv;
  int rrdargc;

  #ifndef RRD
  rrdtool_stdin=popen("rrdtool -", "w");
  #endif
  for(i=0; i<rrd_target_count; i++){
    printf("rrdcmd: %s\n", rrd_targets[i].rrdcmd);
    #ifdef RRD
    //internal rrd_update
    sprintf(rrdcmd,"%s", rrd_targets[i].rrdcmd);
    rrdargv = string_to_argv(rrdcmd, &rrdargc);
    rrd_update(rrdargc, rrdargv);
    free(rrdargv);
    #else
    //external rrdtool update with remote control
    fprintf(rrdtool_stdin, "%s\n",rrd_targets[i].rrdcmd);
    #endif
  }
  #ifndef RRD
  pclose(rrdtool_stdin);
  #endif
}

int update_multirrd(){

}


char *rrdcmd_lli(char *rrd_name, char *rrd_path, unsigned long long int result){
  char rrdcmd[512];

  sprintf(rrdcmd, "update %s --template %s N:%lli", rrd_path, rrd_name, result);
  return rrdcmd;
}

char *rrdcmd_string(char *rrd_path, char *stringresult, int local_data_id){
  extern conf_t conf;
  char *p, *tokens[64];
  char rrdcmd[512] ="update ";
  char *last;
  char query[256];
  int i = 0;
  int j = 0;
  MYSQL mysql;
  MYSQL_RES *result;
  MYSQL_ROW row;
  mysql_init(&mysql);
  if (!mysql_real_connect(&mysql, conf.sqlhost, conf.sqluser, conf.sqlpw, conf.sqldb, 0, NULL, 0)){
    fprintf(stderr, "%s\n", mysql_error(&mysql));
    exit(1);
  }

  for((p = strtok_r(stringresult, " :", &last)); p; (p = strtok_r(NULL, " :", &last)), i++) tokens[i] = p;
  tokens[i] = NULL;

  strcat(rrdcmd, rrd_path);
  strcat(rrdcmd, " --template ");
  for(j=0; j<i; j=j+2){
    if(j!=0) strcat(rrdcmd, ":");
    //sql
    sprintf(query, "select rrd_data_source_name from data_input_data_fcache where \
      local_data_id=%i and data_input_field_name=\"%s\"", local_data_id, tokens[j]);
    if (mysql_query(&mysql, query)) fprintf(stderr, "Error in query\n");
    if ((result = mysql_store_result(&mysql)) == NULL){
      fprintf(stderr, "Error retrieving data\n");
      exit(1);
    }
    row = mysql_fetch_row(result);
    strcat(rrdcmd, row[0]);
  }
  strcat(rrdcmd, " N");
  for(j=1; j<i; j=j+2){
    strcat(rrdcmd, ":");
    strcat(rrdcmd, tokens[j]);
  }
  return rrdcmd;
}

