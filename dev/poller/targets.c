#include "inc.h"

int get_targets(){
  char query[256];
  extern target_t *targets;
  target_t *temp;
  target_t *temp2;
  MYSQL mysql;
  MYSQL_RES *result;
  MYSQL_ROW row;
  char db[256];
  char dbhost[256];
  char dbuser[256];
  char dbpass[256];
  sprintf(db,"cacti");
  sprintf(dbhost,"localhost");
  sprintf(dbuser,"root");
  sprintf(dbpass,"");
  mysql_init(&mysql);
  if (!mysql_real_connect(&mysql, dbhost, dbuser, dbpass, db, 0, NULL, 0)){
    fprintf(stderr, "%s\n", mysql_error(&mysql));
    exit(1);
  }
  sprintf(query, "select host,comm,oid,rrd from targets");
  if (mysql_query(&mysql, query)) fprintf(stderr, "Error in query\n");
  if ((result = mysql_store_result(&mysql)) == NULL){
    fprintf(stderr, "Error retrieving data\n");
    exit(1);
  }
  mysql_close(&mysql);
  free(targets);
  targets=NULL;
  while ((row = mysql_fetch_row(result))) {
    temp = (target_t *) malloc(sizeof(target_t));
    sprintf(temp->host, row[0]);
    sprintf(temp->community, row[1]);
    sprintf(temp->oid, row[2]);
    sprintf(temp->rrd, row[3]);
    temp->prev=NULL;
    temp->next=NULL;
    if(targets == NULL) targets = temp;
    else{
      for(temp2 = targets; temp2->next !=NULL; temp2 = temp2->next);
      temp->prev = temp2;
      temp2->next = temp;
    }
  }
  temp=NULL;
  free(temp);
  temp2=NULL;
  free(temp2);
  return mysql_num_rows(result);;
}
