#CC=gcc-3.2 -Wall

#MySQL paths
INC+=-I/usr/include/mysql
LIB+=-L/usr/lib

#NET-SNMP
DEF=-DNETSNMP
INC+=-I/usr/include/net-snmp
LIB+=-L/usr/lib -lsnmp

#UCD-SNMP
#DEF=-DUCDSNMP
#INC+=-I/usr/include/ucd-snmp
#LIB+=-L/usr/lib

#RRD path
INC+=-I/usr/include
LIB+=-L/usr/lib

#PThread path
INC+=-I/usr/include
LIB+=-L/usr/lib

LIB+=-lmysqlclient -lrrd -lpthread

OBJS=snmp.o sighandler.o poller.o targets.o rrd.o conf.o main.o

all: poller

poller: $(OBJS)
	$(CC) $(OBJS) -o poller $(LIB) 

clean:
	rm -f $(OBJS) poller

.c.o:
	$(CC) -c $< -o $@ $(DEF) $(INC)
	
