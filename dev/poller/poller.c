#include "inc.h"

extern target_t *current;
extern int entries;

void *poller(){
  target_t *entry = NULL;
  rrd_t *rrd_targets = (rrd_t *)malloc(entries * sizeof(rrd_t));
  multi_rrd_t *rrd_multids;
  unsigned long long result = 0;
  FILE *cmd_stdout;
  char cmd_result[64];
  int current_head=0;
  int rrd_target_counter=0;
  int current_local_data_id=0;
  int rrd_multids_counter=0;



  //polling stuff
  while(current != NULL && current_head==0){
    entry = current;
    if(current->next != NULL) current = current->next;
    else{
      current = current->head;
      current_head=1;
    }

    switch(entry->action) {
      case 0:
        #ifdef TEST
        entry->result=1;
        #else
        entry->result=snmp_get(entry->management_ip, entry->snmp_community, entry->snmp_version, entry->arg1,0);
        #endif
      break;
      case 1:
        cmd_stdout=popen(entry->command, "r");
        if(cmd_stdout != NULL) fgets(cmd_result, 64, cmd_stdout);
        if(is_number(cmd_result)) entry->result = atoll(cmd_result);
        pclose(cmd_stdout);
      break;
      //Action 2
      case 2:
        cmd_stdout=popen(entry->command, "r");
        if(cmd_stdout != NULL) fgets(cmd_result, 64, cmd_stdout);
        printf("cmd_result: %s\n", cmd_result);
        pclose(cmd_stdout);
        sprintf(entry->stringresult, "%s", cmd_result);
        entry->result=0;
      break;
      default:
        printf("Unknown Action(%i)!\n",entry->action);
        entry->result=0;
      break;
    }
  }
  //new round
  current_head=0;

  printf("Polling Done\n");

  //result stuff
  while(current != NULL && current_head==0){
    entry = current;
    if(current->next != NULL) current = current->next;
    else{
      current = current->head;
      current_head=1;
    }

    printf("management_ip: %s target_id: %i result: %lli\n",entry->management_ip, entry->target_id, entry->result);

    if(/*current_head==0 && */entry->local_data_id == current->local_data_id){
      printf("Multi DS RRA\n");
      if(entry->local_data_id != current_local_data_id){
        printf("New MultiDS: %i\n", entry->local_data_id);
        rrd_multids = (rrd_t *)malloc(entries * sizeof(rrd_t));
        rrd_multids_counter=0;
        sprintf(rrd_multids[rrd_multids_counter].rrd_name, "%s", entry->rrd_name);
        sprintf(rrd_multids[rrd_multids_counter].rrd_path, "%s", entry->rrd_path);
        rrd_multids[rrd_multids_counter].result = entry->result;
        rrd_multids_counter++;
        current_local_data_id = entry->local_data_id;
      } else if(entry->local_data_id == current_local_data_id){
        printf("Old MultiDS: %i\n", entry->local_data_id);
        sprintf(rrd_multids[rrd_multids_counter].rrd_name, "%s", entry->rrd_name);
        sprintf(rrd_multids[rrd_multids_counter].rrd_path, "%s", entry->rrd_path);
        rrd_multids[rrd_multids_counter].result = entry->result;
        rrd_multids_counter++;
      }
    } else if(/*current_head==0 && */entry->local_data_id == current_local_data_id && current->local_data_id != current_local_data_id){
      printf("Last MultiDS: %i\n", entry->local_data_id);
      sprintf(rrd_multids[rrd_multids_counter].rrd_name, "%s", entry->rrd_name);
      sprintf(rrd_multids[rrd_multids_counter].rrd_path, "%s", entry->rrd_path);
      rrd_multids[rrd_multids_counter].result = entry->result;
      //plah
      sprintf(rrd_targets[rrd_target_counter].rrdcmd, "%s", rrdcmd_multids(rrd_multids,rrd_multids_counter));
      rrd_target_counter++;
      free(rrd_multids);
      current_local_data_id=0;
    } else if(entry->action==2){
      printf("String result\n");
      printf("ldid: %i\n", entry->local_data_id);
      sprintf(rrd_targets[rrd_target_counter].rrdcmd, "%s", rrdcmd_string(entry->rrd_path, entry->stringresult, entry->local_data_id));
      rrd_target_counter++;
    } else {
      printf("Single DS RRA\n");
      sprintf(rrd_targets[rrd_target_counter].rrdcmd, "%s", rrdcmd_lli(entry->rrd_name, entry->rrd_path, entry->result));
      rrd_target_counter++;
    }
  }
  printf("count: %i\n", entries);
  update_rrd(rrd_targets, rrd_target_counter);
  free(rrd_targets);
}
