#include "inc.h"

//read config file
int read_conf(){
  extern conf_t conf;
  FILE *cfile;
  char buffer[256];
  
  cfile=fopen("cactid.conf","r");
  if(cfile!=NULL){
    fscanf(cfile, " %s %s\n", buffer, (conf.sqluser));
    fscanf(cfile, " %s %s\n", buffer, (conf.sqlpw));
    if(!strcmp(conf.sqlpw,"none")) sprintf(conf.sqlpw, "");
    fscanf(cfile, " %s %s", buffer, (conf.sqlhost));
    fscanf(cfile, " %s %s", buffer, (conf.sqldb));
    fscanf(cfile, " %s %i", buffer, &(conf.interval));
    fclose(cfile);
  } else {
    printf("conf not found\n");
    exit(1);
  }
  printf("CONF: user: %s\n", conf.sqluser);
  printf("CONF: pw: %s\n", conf.sqlpw);
  printf("CONF: host: %s\n", conf.sqlhost);
  printf("CONF: db: %s\n", conf.sqldb);
  printf("CONF: interval: %i\n", conf.interval);
}
