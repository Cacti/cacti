#include "inc.h"

int collect(target_t *entries, int worker_index){

  unsigned long long result = 0;
  FILE *cmd_stdout;
  char cmd_result[64];
  target_t *entry;
  char rrdcmd[512];
  char rrdcmdds[512]="";
  char rrdcmdval[512];
  char **rrdargv;
  int rrdargc, temp_local_data_id;

  if(entries->next != NULL) {
    printf("MultiDS\n");
    entry = entries;
    while(entries != NULL){
      if(entry->action == 0){
        //do snmp stuff
        printf("[%i] snmp_get(%s,%s,%i,%s,%i)\n",worker_index, entry->management_ip, entry->snmp_community, entry->snmp_version, entry->arg1, worker_index);
//        result = snmp_get(entry->management_ip, entry->snmp_community, entry->snmp_version, entry->arg1, worker_index);
        printf("[%i] snmp_get() done\n",worker_index);
      }

      printf("[%i] got: (%llu)\n",worker_index, result);

      printf("%s\n", rrdcmdval);
      sprintf(rrdcmdds,"%s:%s", rrdcmdds, entry->rrd_name);
      sprintf(rrdcmdval, "%s:%llu", rrdcmdval, result);
      if(entries->next != NULL) {
        entries = entries->next;
        entry = entries;
      }
      else entries = NULL;
      
    }
   #ifdef RRD
    //internal rrd_update
    sprintf(rrdcmd,"update %s %s N%s", entry->rrd_path, rrdcmdds, rrdcmdval);
    printf("RRD: rrd_update(%s)\n",rrdcmd);
    rrdargv = string_to_argv(rrdcmd, &rrdargc);
    rrd_update(rrdargc, rrdargv);
  #else
    //external rrdtool command
    sprintf(rrdcmd,"rrdtool update %s %s N%s", entry->rrd_path, rrdcmdds, rrdcmdval);
    printf("RRD: %s\n",rrdcmd);
    system(rrdcmd);
  #endif
 
  }
  else {
    printf("SingleDS\n");
    entry = entries;
    if(entry->action == 0){
      //do snmp stuff
      printf("[%i] snmp_get(%s,%s,%i,%s,%i)\n",worker_index, entry->management_ip, entry->snmp_community, entry->snmp_version, entry->arg1, worker_index);
      result = snmp_get(entry->management_ip, entry->snmp_community, entry->snmp_version, entry->arg1, worker_index);
      printf("[%i] snmp_get() done\n",worker_index);
    }  
    else if(entry->action == 1){
      printf("[%i] exec(%s)\n", worker_index, entry->command);
      cmd_stdout=popen(entry->command, "r");
      if(cmd_stdout != NULL) fgets(cmd_result, 64, cmd_stdout);
      printf("[%i] exec() done\n", worker_index);
      if(is_number(cmd_result)) result = atoll(cmd_result);
    }
    printf("[%i] got: (%llu)\n",worker_index, result);
    #ifdef RRD
      //internal rrd_update
      sprintf(rrdcmd,"update %s %s N:%llu", entry->rrd_path, entry->rrd_name, result);
      printf("RRD: rrd_update(%s)\n",rrdcmd);
      rrdargv = string_to_argv(rrdcmd, &rrdargc);
      rrd_update(rrdargc, rrdargv);
    #else
      //external rrdtool command
      sprintf(rrdcmd,"rrdtool update %s %s N:%llu", entry->rrd_path, entry->rrd_name, result);
      printf("RRD: %s\n",rrdcmd);
      system(rrdcmd);
    #endif

  }
  printf("collectime %s\n", entry->management_ip);
//  sleep(60);
}

