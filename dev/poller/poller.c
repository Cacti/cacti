#include "inc.h"

extern target_t *current;

void *poller(void *thread_args){

  thread_t *worker = (thread_t *) thread_args;
  threads_t *threads = worker->threads;
  target_t *entry = NULL;
  char rrdcmd[512];
  char **rrdargv;
  int rrdargc;
  unsigned long long result = 0;
  FILE *cmd_stdout;
  char cmd_result[64];
  while(1){
    printf("[%i] wait lock\n", worker->index);
    if(pthread_mutex_lock(&threads->mutex) != 0) \
      printf("pthread_mutex_lock error\n");

    while(current == NULL){
      printf("[%i] Queue emty\n", worker->index);
      if(pthread_cond_wait(&threads->work, &threads->mutex) != 0) \
        printf("pthread_cond_wait error\n");
    }
    printf("[%i] work (queue %d)\n", worker->index, threads->work_count);
    if (current != NULL) {
      printf("[%i] get %s %i (queue %d)\n", worker->index, current->management_ip, \
        current->action, threads->work_count);
      entry = current;
      if (current->next != NULL){
        current = current->next;
        if(entry->local_data_id == current->local_data_id) printf("multi DS rra\n");
      } else current = NULL;
      printf("debug1\n");
      if(entry->action == 0){
      //do snmp stuff
        printf("[%i] snmp_get(%s,%s,%i,%s,%i)\n",worker->index, entry->management_ip, \
          entry->snmp_community, entry->snmp_version, entry->arg1, worker->index);
        result = snmp_get(entry->management_ip, entry->snmp_community, \
          entry->snmp_version, entry->arg1, worker->index);
        printf("[%i] snmp_get() done\n",worker->index);
      } else if(entry->action == 1){
        printf("debug\n");
	result = 0;
      //execute external program and read data from stdout
        printf("[%i] exec(%s)\n", worker->index, entry->command);
        cmd_stdout=popen(entry->command, "r");
        if(cmd_stdout != NULL) fgets(cmd_result, 64, cmd_stdout);
        printf("[%i] exec() done\n", worker->index);
        if(is_number(cmd_result)) result = atoll(cmd_result);
      } else printf("[%i] unknown action %i\n", worker->index, entry->action);
      
      printf("[%i] got: (%llu)\n",worker->index, result);

      printf("[%i] unlock queue\n", worker->index);
      if (pthread_mutex_unlock(&threads->mutex) != 0) \
        printf("pthread_mutex_unlock error\n");
      if(result!=0) {
        printf("[%i] got: (%llu)\n",worker->index, result);
	
	#ifdef RRD
	  //internal rrd_update
          sprintf(rrdcmd,"update %s/%s N:%llu", entry->rrd_path, entry->rrd_name, result);
          printf("RRD: rrd_update(%s)\n",rrdcmd);
          rrdargv = string_to_argv(rrdcmd, &rrdargc);
          rrd_update(rrdargc, rrdargv);
	#else
	  //external rrdtool command
          sprintf(rrdcmd,"rrdtool update %s/%s N:%llu", entry->rrd_path, entry->rrd_name, result);
          printf("RRD: %s\n",rrdcmd);
          system(rrdcmd);
	#endif
      }

      printf("[%i] lock work_count\n", worker->index);
      if (pthread_mutex_lock(&threads->mutex) != 0) printf("pthread_mutex_lock error\n");
      threads->work_count--;
      if (threads->work_count <= 0) {
        printf("[%i] done\n", worker->index);
        if (pthread_cond_broadcast(&threads->done) != 0) printf("pthread_cond_broadcast error\n");
      }
      printf("[%i] unlock work_count\n", worker->index);
      if(pthread_mutex_unlock(&threads->mutex) != 0) printf("pthread_mutex_unlock error\n");
    }
  }
}
