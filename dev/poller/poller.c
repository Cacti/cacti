#include "inc.h"

extern target_t *current;

void *poller(void *thread_args){

  thread_t *worker = (thread_t *) thread_args;
  threads_t *threads = worker->threads;
  target_t *entry = NULL;
  target_t *thread_entry = NULL;
  target_t *temp2 = NULL;
  int temp_local_data_id;
  while(1){
    printf("[%i] wait lock\n", worker->index);
    if(pthread_mutex_lock(&threads->mutex) != 0) printf("pthread_mutex_lock error\n");

    while(current == NULL){
      printf("[%i] Queue emty\n", worker->index);
      if(pthread_cond_wait(&threads->work, &threads->mutex) != 0) printf("pthread_cond_wait error\n");
    }

    thread_entry=NULL;
    printf("[%i] work (queue %d)\n", worker->index, threads->work_count);
    if (current != NULL) {
      entry = current;
      if(current->next != NULL) current = current->next;
      else current = NULL;
      temp_local_data_id = entry->local_data_id;
      if(current != NULL){
        printf("if(%i == %i)\n", temp_local_data_id, current->local_data_id);
        if(temp_local_data_id == current->local_data_id){
          // Multi DS rra
          printf("multi DS rra\n");
          while(temp_local_data_id == entry->local_data_id){
            printf("multi entry: %s %s\n", entry->management_ip, entry->arg1);
//            if(thread_entry == NULL) thread_entry = entry;
//            else thread_entry->next = entry;
//

    entry->prev=NULL;
    entry->next=NULL;
    if(thread_entry == NULL) thread_entry = entry;
    else{
      for(temp2 = thread_entry; temp2->next !=NULL; temp2 = temp2->next);
      entry->prev = temp2;
      temp2->next = entry;
    }


//
            threads->work_count--;
            if(temp_local_data_id == current->local_data_id) {
              entry = current;
              if(current->next != NULL) current = current->next;
              else current = NULL;
            } else break;
          }
        // Single DS rra
        } else {
          printf("entry: %s %s\n", entry->management_ip, entry->arg1);
          thread_entry = entry;
          threads->work_count--;
        } 
      } else {
        printf("entry: %s %s\n", entry->management_ip, entry->arg1);
        thread_entry = entry;
        threads->work_count--;
      }

      temp_local_data_id = 0;
      printf("asdasdasd work: %i\n", threads->work_count); //debug
      //thread_entry->next=NULL;



      printf("[%i] unlock queue\n", worker->index);
      if (pthread_mutex_unlock(&threads->mutex) != 0) printf("pthread_mutex_unlock error\n");

collect(thread_entry, worker->index);

//      printf("[%i] lock work_count\n", worker->index);
//      if (pthread_mutex_lock(&threads->mutex) != 0) printf("pthread_mutex_lock error\n");
//      threads->work_count--;
      
      if (threads->work_count <= 0) {
        printf("[%i] done\n", worker->index);
        if (pthread_cond_broadcast(&threads->done) != 0) printf("pthread_cond_broadcast error\n");
      }
//      printf("[%i] unlock work_count\n", worker->index);
//      if(pthread_mutex_unlock(&threads->mutex) != 0) printf("pthread_mutex_unlock error\n");
    }
  }
}
