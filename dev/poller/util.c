#include "inc.h"

char** string_to_argv(char *argstring, int *argc){
  char inquotes = 0, *workstring, **argv;
  int i, nchars;

  if((nchars = strlen(argstring)) > 0) {
    workstring = (char*)calloc((nchars + 2), sizeof(char*));
    for(i=0; i < (nchars + 2); i++) workstring[i] = ' ';
    for(i=0; i < nchars; i++) workstring[i+1] = argstring[i];
    for((*argc) = 1, i=0; i < nchars + 2; i++){
      if(isgraph(workstring[i]) > 0){
        if((isgraph(workstring[i-1]) == 0) && (inquotes == 0)) (*argc)++;
        if(workstring[i] == '"') inquotes = (char)(!inquotes);
        else if(!inquotes) workstring[i] = '\0';
      }
    }
    if((*argc) == 0){
      free(workstring);
      return NULL;
    } else {
      inquotes = 0;
      argv = (char**)calloc((*argc), sizeof(char**));
      argv[0] = &workstring[0];
      for((*argc) = 1, i=1; i < nchars + 2; i++){
        if(isgraph(workstring[i]) > 0){
          if((isgraph(workstring[i-1]) == 0) && (inquotes == 0)){
            argv[(*argc)] = &workstring[i];
            (*argc)++;
          }
          if(workstring[i] == '"') inquotes = (char)(!inquotes);
        }
      }
      return argv;
    }
  } else {
    (*argc) = 0;
    return NULL;
  }
}

int is_number (char *string){
  int i;
  for(i=0; i<strlen(string); i++) {
    if(!isdigit(string[i]) && !(i==strlen(string)-1 && isspace(string[i]))) return(0);
  }
  return(1);
}

