#include "inc.h"

target_t *targets = NULL;
target_t *current = NULL;
int entries = 0;
conf_t conf;

int main(void){
  int i;
  printf("INIT: reading conf\n");
  read_conf();

  printf("INIT: jobs\n");
  entries = get_targets();
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

