#include "inc.h"

target_t *current = NULL;
target_t *targets = NULL;
int entries = 0;
conf_t conf;

int main(void){
  threads_t threads;
  pthread_t sig_thread;
  sigset_t signal_set;
  int i;
  printf("INIT: reading conf\n");
  read_conf();
  printf("INIT: signal handlers\n");
  sigemptyset(&signal_set);
  sigaddset(&signal_set, SIGHUP);
  if(pthread_sigmask(SIG_BLOCK, &signal_set, NULL) != 0) printf("pthread_sigmask error\n");

  printf("INIT: jobs\n");
  entries = get_targets();
  if(entries==0) printf("INIT: No jobs\n");

  printf("INIT: SNMP\n");
  init_snmp("Cacti Poller");

  printf("INIT: create threads\n");
  pthread_mutex_init(&(threads.mutex), NULL);
  pthread_cond_init(&(threads.done), NULL);
  pthread_cond_init(&(threads.work), NULL);
  threads.work_count = 0;
  for(i = 0; i < THREADS; i++){
    threads.member[i].index = i;
    threads.member[i].threads = &threads;
    if(pthread_create(&(threads.member[i].thread), NULL, poller, (void *) &(threads.member[i])) != 0) printf("pthread_create error\n");
  }
    if(pthread_create(&sig_thread, NULL, sig_handler, (void *) &(signal_set)) != 0) printf("pthread_create error\n");
  sleep(1);

  printf("INIT: ready\n");

  while(1){
    lock = TRUE;
    if(pthread_mutex_lock(&(threads.mutex)) != 0) printf("pthread_mutex_lock error\n");
    current = targets;
    threads.work_count = entries;
    if(pthread_mutex_unlock(&(threads.mutex)) != 0) printf("pthread_mutex_unlock error\n");
    if(pthread_cond_broadcast(&(threads.work)) != 0) printf("pthread_cond_broadcast error\n");
    if(pthread_mutex_lock(&(threads.mutex)) != 0) printf("pthread_mutex_lock error\n");
    while(threads.work_count > 0){
      printf("Work_count: %i\n",threads.work_count);
      if(pthread_cond_wait(&(threads.done), &(threads.mutex)) != 0) printf("thread_cont_wait error\n");
    }
    if(pthread_mutex_unlock(&(threads.mutex)) != 0) printf("pthread_mutex_unlock error\n");
    lock = FALSE;
    sleep(conf.interval);
    if(sighup){
      entries = get_targets();
      sighup = FALSE;
    }
  }
}

