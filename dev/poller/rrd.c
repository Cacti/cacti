#include "inc.h"

int update_rrd(rrd_t *rrd_targets, int rrd_target_count){
  int i;
  FILE *rrdtool_stdin;
  char rrdcmd[512];
  char **rrdargv;
  int rrdargc;

  #ifndef RRD
  rrdtool_stdin=popen("rrdtool -", "w");
  #endif
  for(i=0; i<rrd_target_count; i++){
    #ifdef RRD
    //internal rrd_update (don't work?)
    sprintf(rrdcmd,"update %s --template %s N:%lli", rrd_targets[i].rrd_path, rrd_targets[i].rrd_name, rrd_targets[i].result);
    rrdargv = string_to_argv(rrdcmd, &rrdargc);
    rrd_update(rrdargc, rrdargv);
    #else
    //external rrdtool update with remote control
    fprintf(rrdtool_stdin, "update %s --template %s N:%lli\n",rrd_targets[i].rrd_path, rrd_targets[i].rrd_name, rrd_targets[i].result);
    #endif
  }
  #ifndef RRD
  pclose(rrdtool_stdin);
  #endif
}

int update_multirrd(){

}