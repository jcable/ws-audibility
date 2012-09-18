import os
os.environ['TRAC_ENV'] = '/var/www/html/audibility/trac'
os.environ['PYTHON_EGG_CACHE'] = '/usr/lib64/python2.6/site-packages/eggs'

import trac.web.main
application = trac.web.main.dispatch_request

