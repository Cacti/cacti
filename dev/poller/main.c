#include "inc.h"

target_t *targets = NULL;
target_t *current = NULL;
conf_t conf;
int entries;
int conf_changed;

void sighup(){
  extern int conf_changed;
  signal(SIGHUP,sighup); /* reset signal */
  printf("SIGHUP\n");
  conf_changed=1;
}

int main(void){
  FILE *fp;
  fp=fopen("/tmp/cactid.pid", "w");
  fprintf(fp, "%i",getpid());
  fclose(fp);

  signal(SIGHUP,sighup);

  printf("INIT: reading conf\n");
  read_conf();

  printf("INIT: jobs\n");
  entries=get_targets();
  if(entries==0) printf("INIT: No jobs\n");

  printf("INIT: SNMP\n");
  init_snmp("Cactid");

  printf("INIT: ready\n");

  current=targets;
  while(1){
    poller();
    sleep(conf.interval);
    if(conf_changed==1){
      printf("conf changed\n");
      entries=get_targets();
      current=targets;
      conf_changed=0;
    }

  }
}

