import sys
import pblib
import pbrest
import requests
import json
import time
import datetime
import threading
import queue
from queue import *
from threading import *
from pblib import debug_log, sanitize_string, config
from pbgooglelib import *
from pbdiscordlib import *

exitFlag = 0
queueLock = threading.Lock()
workQueue = queue.Queue(300)
threadID = 1
threads = []

class puzzThread (threading.Thread):
   def __init__(self, threadID, name, q, fromtime=datetime.datetime.fromordinal(1)):
      threading.Thread.__init__(self)
      self.threadID = threadID
      self.name = name
      self.q = q
      self.fromtime = fromtime
   def run(self):
      check_puzzle_from_queue(self.name, self.q, self.fromtime)
      debug_log(4, "Exiting puzzthread %s" % self.name)

def check_puzzle_from_queue(threadname, q, fromtime):
    while not exitFlag:
        queueLock.acquire()
        if not workQueue.empty():
            mypuzzle = q.get()
            queueLock.release()
            debug_log(4, "[Thread: %s] Fetched from queue puzzle: %s" % (threadname, mypuzzle['name']))
            
            # Lots of annoying time string conversions here between mysql and google
            lastpuzzleacttime = datetime.datetime.fromordinal(1)
            if mypuzzle['lastact']:
                lastpuzzleacttime = datetime.datetime.strptime(mypuzzle['lastact']['timestamp'], '%a, %d %b %Y %H:%M:%S %Z')
            
            # Go through all revisions for the puzzle and see if any are relevant ("new")
            for revision in (get_revisions(mypuzzle['drive_id'])):
                revisiontime = datetime.datetime.strptime(revision['modifiedTime'], '%Y-%m-%dT%H:%M:%S.%fZ')    
                
                if revisiontime > fromtime:
                    # This is a new revision since last loop, give or take a few minutes.
                    debug_log(4, "[Thread: %s] relatively recent revision found by %s on %s" 
                              % (threadname, revision['lastModifyingUser']['emailAddress'], mypuzzle['name']))
                    debug_log(4, "[Thread: %s] previous last activity on this puzzle is %s" % (threadname, lastpuzzleacttime))
                    
                    if revisiontime > lastpuzzleacttime:
                        # This revision is newer than any other activity already associated with the puzzle
                        debug_log(3, "[Thread: %s] this is a newly discovered revision on puzzle id %s by %s! Adding to activity table."
                                  % (threadname, mypuzzle['id'], revision['lastModifyingUser']['emailAddress']))
                        
                        if solver_from_email(revision['lastModifyingUser']['emailAddress']) == 0:
                            debug_log(1, "[Thread: %s] solver %s not found in solver db? This shouldn't happen. Skipping revision." % 
                                      (threadname, revision['lastModifyingUser']['emailAddress']))
                            continue
                        
                        databody = {
                                    "lastact" : {
                                                 "solver_id" : "%s" % solver_from_email(revision['lastModifyingUser']['emailAddress']),
                                                 "source" : "google",
                                                 "type" : "revise"
                                                }
                                    }      
                        actupresponse = requests.post("%s/puzzles/%s/lastact" % 
                                                     (config['BIGJIMMYBOT']['APIURI'], mypuzzle['id']), json = databody)
                        
                        debug_log(4, "[Thread: %s] Posted update %s to last activity for puzzle.  Response: %s" % (threadname, databody, actupresponse.text))
                        #TODO: check solver for more recent activity and then consider forcing them onto this puzzle
                    
        else:
            queueLock.release()
    return(0)

def solver_from_email(email):
    debug_log(4, "start. called with %s" % email)
    solverslist = json.loads(requests.get("%s/solvers" % config['BIGJIMMYBOT']['APIURI']).text)['solvers']
    for solver in solverslist:
        if solver['name'] == email.split('@')[0]:
            debug_log(4, "Solver %s is id: %s" % (email, solver['id']))
            return(solver['id'])      
    return(0)                            

if __name__ == '__main__':
    if initdrive() != 0:
        debug_log(0, "google drive init failed. Fatal.")
        sys.exit(255)
    
    debug_log(3, "google drive init succeeded. Hunt folder id: %s" % pblib.huntfolderid)    

    while True:
        r = json.loads(requests.get("%s/rounds" % config['BIGJIMMYBOT']['APIURI']).text)
        debug_log(5, "api return: %s" % r)
        rounds = r['rounds']
        debug_log(4, "loaded round list")
        puzzles = []
        for round in rounds:
            puzzlesinround = json.loads(requests.get("%s/rounds/%s/puzzles" % 
                                                     (config['BIGJIMMYBOT']['APIURI'], round['id'])).text)['round']['puzzles']
            debug_log(4, "appending puzzles from round %s: %s" % (round['id'], puzzlesinround))
            for puzzle in puzzlesinround:
                if puzzle['status'] != 'Solved':
                    puzzles.append(puzzle)
                else:
                    debug_log(4, "skipping solved puzzle %s" % puzzle['name'])
        debug_log(4, "full puzzle structure loaded")
        fromtime = datetime.datetime.utcnow() - datetime.timedelta(minutes=5)
        debug_log(3, "Beginning iteration of bigjimmy bot across all puzzles (checking revs from time %s)" % fromtime)
        
        # initialize threads
        for i in range(1,config['BIGJIMMYBOT']['THREADCOUNT'] + 1):
            thread = puzzThread(threadID, i, workQueue, fromtime)
            thread.start()
            threads.append(thread)
            threadID += 1
            
        # put all puzzles in the queue so work can start
        queueLock.acquire()
        for puzzle in puzzles:
            workQueue.put(puzzle)
        queueLock.release()
        
        # wait for queue to be completed
        while not workQueue.empty():
            pass
        
        # Notify threads to exit
        exitFlag = 1
        
        # Wait for all threads to rejoin
        for t in threads:
            t.join()
            
        debug_log(3, "Completed iteration of bigjimmy bot across all puzzles from time %s" % fromtime)
        exitFlag = 0
        time.sleep(config['BIGJIMMYBOT']['LOOPPAUSETIME'])
           