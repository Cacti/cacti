#include "inc.h"

extern target_t *current;

void *poller(void *thread_args){

  thread_t *worker = (thread_t *) thread_args;
  threads_t *threads = worker->threads;
  target_t *entry = NULL;
  char rrdcmd[512];
  unsigned long long result = 0;
  while(1){
    printf("[%d] wait lock\n", worker->index);
    if(pthread_mutex_lock(&threads->mutex) != 0) printf("pthread_mutex_lock error\n");
    while(current == NULL){
      printf("[%i] Queue emty\n", worker->index);
      if(pthread_cond_wait(&threads->work, &threads->mutex) != 0) printf("pthread_cond_wait error\n");
    }
    printf("[%d] work (queue %d)\n", worker->index, threads->work_count);
    if (current != NULL) {
      printf("[%d] get %s %s (queue %d)\n", worker->index, current->host, current->oid, threads->work_count);
      entry = current;
      if (current->next != NULL) current = current->next;
      else current = NULL;

      printf("snmp_get(%s,%s,%s,%i)\n",entry->host, entry->community, entry->oid, worker->index);
      result = snmp_get(entry->host, entry->community, entry->oid, worker->index);
      printf("[%i] snmp_get() done\n",entry->index);
      printf("[%d] got: (%llu)\n",worker->index, result);
      printf("[%d] unlock queue\n", worker->index);
      if (pthread_mutex_unlock(&threads->mutex) != 0) printf("pthread_mutex_unlock error\n");
      if(result!=0) {
        printf("[%d] got: (%llu)\n",worker->index, result);
        sprintf(rrdcmd,"rrdtool update %s N:%llu", entry->rrd, result);
        printf("RRD: %s\n",rrdcmd);
        //wrap_rrd_update(rrdcmd);
        //system(rrdcmd);
      }

      printf("[%d] lock work_count\n", worker->index);
      if (pthread_mutex_lock(&threads->mutex) != 0) printf("pthread_mutex_lock error\n");
      threads->work_count--;
      if (threads->work_count <= 0) {
        printf("[%d] done\n", worker->index);
        if (pthread_cond_broadcast(&threads->done) != 0) printf("pthread_cond_broadcast error\n");
      }
      printf("[%d] unlock work_count\n", worker->index);
      if(pthread_mutex_unlock(&threads->mutex) != 0) printf("pthread_mutex_unlock error\n");
    }
  }
}
