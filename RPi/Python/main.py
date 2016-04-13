# final_project_main.py
# CB: Michael Kukar 2016
# Runs the code for the final project sprinkler system

# interacts with a save file that is generated using a mysql database
# the web interface writes to this database, we read from it and control the sprinklers

import time, datetime

from database_handler import *
from water_cycle import *
from atmega import *

import socket

# PURPOSE OF MAIN PROGRAM
# runs a continuous loop that does a multitude of things
# 1. keep track of time and spawn programs throughout the day
# 2. check every so often the data and update all the tables appropriately (read from them too)
# 3. make new predictions every so often based on new data

# ################
# HELPER FUNCTIONS
# ################

# updates the variables so we know what schedule to run today
def updateSchedulePlan(todaySchedule):
    global runProgram1
    global runProgram2
    global runProgram3
    global ranProgram1Today
    global ranProgram2Today
    global ranProgram3Today

    print("UPDATING SCHEDULE PLAN")
    for char in todaySchedule: # iterates across all the characters (up to 1, 2, 3 123 eg.)
        if char == '1':
            print("PROG1 ADDED")
            runProgram1 = True
        elif char == '2':
            print("PROG2 ADDED")
            runProgram2 = True
        elif char == '3':
            print("PROG3 ADDED")
            runProgram3 = True

    # resets our programs run today
    ranProgram1Today = False
    ranProgram2Today = False
    ranProgram3Today = False

def startTestMode(zoneNumber):
    global testMode
    global runDuration
    global runningProgram
    print("STARTED TEST MODE")
    testMode = True
    waterCycler.enterTestMode(zoneNumber)
    runDuration = 5 * 600 # 5 minutes until it defaults back off
    runningProgram = True

# #########
# VARIABLES
# #########

# classes
db = Database_Handler()
waterCycler = Water_Cycle()
arduino = Atmega()

# general variables
schedule = db.getSchedule()

curDay = datetime.datetime.today().weekday() # 0 is Monday, 6 is Sunday
print("CUR DAY: " + str(curDay))
curTime = datetime.datetime.now()
curMilTime = (curTime.hour * 100) + curTime.minute
print("CUR TIME: " + str(curMilTime))

# make sure we run programs sequentially and don't run them concurrently
testMode = False
runProgram1 = False
runProgram2 = False
runProgram3 = False
ranProgram1Today = False
ranProgram2Today = False
ranProgram3Today = False
runningProgram = False
runDuration = 0 # duration of the current running program (time remaining)
# updates the variables for this schedule
updateSchedulePlan(schedule[curDay])

# SOCKET

# sets up socket information
myPortNum = 8675

# BASED ON UDP (DATAGRAM)

# creates socket to listen on
sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)

# binds the socket to our random port number on our local IP
server_address = ('localhost', myPortNum)
sock.bind(server_address)
sock.setblocking(0) # makes the socket not wait for data

# TESTING ONLY
# we set each program to start today and be one minute each and start at this current time
#db.setScheduleIndex("123", curDay + 1)
#db.setProgram(['2', '0', '0', '0', '0', '0', str(curMilTime)], 1)
#db.setProgram(['0', '1', '0', '0', '0', '0', str(curMilTime)], 2)
#db.setProgram(['0', '0', '1', '0', '0', '0', str(curMilTime)], 3)

print("SCHEDULE " + str(db.getSchedule()))
print("PROGRAM 1 " + str(db.getProgram(1)))
print("PROGRAM 2 " + str(db.getProgram(2)))
print("PROGRAM 3 " + str(db.getProgram(3)))

while(True):

    # checks for any new messages and passes them to the message handler
    try:
        data, address = sock.recvfrom(4096)
        if data:
            messageBack = "Recieved." # defaults to this
            if data[0] == 'z': # zone command
                print("zone test")
                startTestMode(int(data[1]))
            elif data == "off":
                print("all off")
                startTestMode(0)
            elif data == "skip":
                print("Skipping today's programs")
                runProgram1 = False
                runProgram2 = False
                runProgram3 = False
                # also enters test mode with all zones off
                startTestMode(0)
            elif data == "data": # send back the soil moisture and temp data
                # RIGHT NOW JUST SOIL MOISTURE
                moisture = arduino.readMoisture()
                messageBack = str(moisture) # just sends back the exact value of the moisture sensor
            # sends back confirmation
            sent = sock.sendto(messageBack, address)

    except:
        pass


    # updates the current date and time
    curTime = datetime.datetime.now()
    curMilTime = (curTime.hour * 100) + curTime.minute
    curDay = datetime.datetime.today().weekday()


    # checks if the schedule was changed at all and updates accordingly
    updatedSchedule = db.getSchedule()
    if schedule[curDay] != updatedSchedule[curDay]:
        schedule = updatedSchedule
        updateSchedulePlan(schedule[curDay])

    # checks if program is running and handles logic for that state
    if runningProgram:
        #print("Program in progress...")
        #print("RUN TIME REMAINING " + str(runDuration))
        runDuration = runDuration - 1 # we cycle every minute, so we subtract one from the run duration
        if runDuration <= 0:
            runningProgram = False
            if testMode: # exits the test mode
                waterCycler.exitTestMode()
                testMode = False



    else: # checks if it is time to run a certain program (anytime after the schedule amount) and then runs it
        if runProgram1 and (curMilTime >= int(db.getProgram(1)[6])) and not ranProgram1Today:
            waterCycler.spawnCycle(db.getProgram(1), 1)
            runningProgram = True
            ranProgram1Today = True
            runDuration = 1 # starts with one minute extra time (for delays, ect.)
            for i in range(len(db.getProgram(1)) - 1):
                runDuration += int(db.getProgram(1)[i])
            runDuration = runDuration * 600
        elif runProgram2 and int(curMilTime) >= int(db.getProgram(2)[6]) and not ranProgram2Today:
            waterCycler.spawnCycle(db.getProgram(2), 2)
            runningProgram = True
            ranProgram2Today = True
            runDuration = 1 # starts with one minute extra time (for delays, ect.)
            for i in range(len(db.getProgram(2)) - 1):
                runDuration += int(db.getProgram(2)[i])
            runDuration = runDuration * 600
        elif runProgram3 and int(curMilTime) >= int(db.getProgram(3)[6]) and not ranProgram3Today:
            waterCycler.spawnCycle(db.getProgram(3), 3)
            runningProgram = True
            ranProgram3Today = True
            runDuration = 1 # starts with one minute extra time (for delays, ect.)
            for i in range(len(db.getProgram(3)) - 1):
                runDuration += int(db.getProgram(3)[i])
            runDuration = runDuration * 600

    time.sleep(0.1) # only sleeps a single second, but we only do things every minute (counter)