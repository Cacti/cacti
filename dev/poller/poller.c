#include "inc.h"

extern target_t *targets;

void *poller(){
  target_t *entry = NULL;
  unsigned long long result = 0;
  FILE *cmd_stdout;
  char cmd_result[64];
  char rrdcmd[512];
  char **rrdargv;
  int rrdargc;

  if(targets==NULL) printf("bqqqq!!!\n");

  while(targets != NULL){
    entry = targets;
    if(targets->next != NULL) targets = targets->next;
    else targets = NULL;
    printf("management_ip: %s\n", entry->management_ip);
    
    if(targets !=NULL && entry->local_data_id == targets->local_data_id){
      printf("Multi DS RRA\n");
      printf("Not Implemented Yet!\n");
    } else {
      printf("Single DS RRA\n");
      switch(entry->action) {
        case 0:
          result=snmp_get(entry->management_ip, entry->snmp_community, entry->snmp_version, entry->arg1,0);
        break;
        case 1:
          cmd_stdout=popen(entry->command, "r");
          if(cmd_stdout != NULL) fgets(cmd_result, 64, cmd_stdout);
          if(is_number(cmd_result)) result = atoll(cmd_result);
        break;
        default:
          printf("Unknown Action!\n");
          result=0;
        break;
      }
      printf("result: %lli\n",result);
      #ifdef RRD
      //internal rrd_update
      sprintf(rrdcmd,"update %s --template %s N:%lli", entry->rrd_path, entry->rrd_name, result);
      printf("RRD: rrd_update(%s)\n",rrdcmd);
      rrdargv = string_to_argv(rrdcmd, &rrdargc);
      rrd_update(rrdargc, rrdargv);
      #else
      //external rrdtool command
      sprintf(rrdcmd,"rrdtool update %s --template %s N:%lli", entry->rrd_path, entry->rrd_name, result);
      printf("RRD: %s\n",rrdcmd);
      system(rrdcmd);
      #endif
    }
  }
  printf("Done!\n");
  
}
