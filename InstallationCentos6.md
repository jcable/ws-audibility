# Introduction #

As installed for the World Service

# Operating System #

Centos installed by downloading and burning the DVD ISO. Web Server installation selected.

Network configured for dual nic active/standby bonding.

# Packages #

Selecting the web server install means apache, sshd, etc. are installed. apache is not active.

PostGIS is not in a Centos repository so the following is needed:

```
cd /etc/yum.repos.d
wget http://ftp.linux.org.tr/epel/6/x86_64/epel-release-6-7.noarch.rpm
rpm -i epel-release-6-7.noarch.rpm
```

Then the required packages can be installed:


```
yum install postgresql postgresql-server
yum install gdal gdal-devel [gdal-java gdal-perl gdal-python]
yum install postgis-utils postgis
```


# Application #