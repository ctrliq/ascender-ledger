# Ansible Ledger
Reporting Tool for Ansible

The idea behind this application is to accept the log stream from Ansible Controller and easily create reports based upon host fact data, view changes that have occurred during playbook runs, and view all host facts.


## Installer
https://github.com/cigamit/ansible-ledger-install

The application consists of 4 roles.  The MySQL Datbase, the Web Server, the Parser (accepts logs from Controller), and a Load Balancer.  

All these roles can reside on the same server or can reside on separate servers.  If using a single instance, the LB is not necessary.  If using the LB, all the instances can be setup in an HA configuration.  Database HA is currently not setup by the installation script (Work in Progress).

## Installation
Using the above installer, follow the directions listed.  It can be installed on most any RHEL based OS (8 / 9).