#include "inc.h"

target_t *targets = NULL;
target_t *current = NULL;
conf_t conf;
int entries;

int main(void){
  printf("INIT: reading conf\n");
  read_conf();

  printf("INIT: jobs\n");
  entries=get_targets();
  if(entries==0) printf("INIT: No jobs\n");

  printf("INIT: SNMP\n");
  init_snmp("Cactid");

  printf("INIT: ready\n");

  while(1){
    current=targets;
    poller();
    sleep(conf.interval);
  }
}

