#include "inc.h"

extern int sighup;

void *sig_handler(void *arg){
  sigset_t *signal_set = (sigset_t *) arg;
  int sig_number;
  while (1){
    sigwait(signal_set, &sig_number);
    if (sig_number == SIGHUP){
      sighup = TRUE;
      printf("HUP!!!!\n");
    }
  }
}
