INC=-I /usr/include/ucd-snmp -I /usr/include/mysql
LIB=-lpthread -lsnmp -lmysqlclient -lrrd

OBJS=snmp.o sighandler.o poller.o targets.o rrd.o main.o

all: poller

poller: $(OBJS)
	$(CC) $(OBJS) -o poller $(LIB) 

clean:
	rm -f $(OBJS) poller

.c.o:
	$(CC) -c $< -o $@ $(INC)
	
