This plugin fork adds custom reports for our internal use:
What each does
1. All Machines by Group
    - This report lists all computers in GLPI, by group.

2. Non-Reservable by Group
    - This report lists assets that have been made unavailable for reservation via the 'Make Unavailable' or 'Prohibit Reservations' button, by group.
3. Reservations by Group
    - This report lists the active reservations of each group.
4. Rolling Average
    - This report lists the rolling average of each group's asset reservation over 9 weeks. Each data point is an average of the previous 9 weeks +- ~0.5%
5. Utilization by Groups
    - This report lists the proportion of time each group has reserved its assets over a given time period.
6. Utilization by Machines
    - This report lists the proportion of time each asset has been reserved for over a given time period, by group.
7. Week over Week Utilization
    - This report lists each group's average asset reservation percentage over the last 9 weeks.

The general strategy used to gather and display data is to use an SQL query to gather as much data as possible, and then use PHP to clean and display it.
