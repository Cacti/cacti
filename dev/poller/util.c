#include "inc.h"

char **string_to_argv(char *argstring, int *argc){
  char *p, **argv;
  char *last;
  int i = 0;

  for((*argc)=1, i=0; i<strlen(argstring); i++) if(argstring[i]==' ') (*argc)++;

  argv = (char **)malloc((*argc) * sizeof(char**));
  for((p = strtok_r(argstring, " ", &last)), i=0; p; (p = strtok_r(NULL, " ", &last)), i++) argv[i] = p;
  argv[i] = NULL;

  return argv;
}

int is_number (char *string){
  int i;
  for(i=0; i<strlen(string); i++) {
    if(!isdigit(string[i]) && !(i==strlen(string)-1 && isspace(string[i]))) return(0);
  }
  return(1);
}