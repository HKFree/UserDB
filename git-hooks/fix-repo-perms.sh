# Run this file from /opt where UserDB and UserDB.git dirs are located.

# We expect that all deployment-responsible users and apache
# belong to the following group.
# Use
#     usermod -a -G userdb some-user
# to do that.


GROUP=userdb

setfacl -R -m g:$GROUP:rwX UserDB
find UserDB -type d | xargs setfacl -R -m d:g:$GROUP:rwX

setfacl -R -m g:$GROUP:rwX UserDB.git
find UserDB.git -type d | xargs setfacl -R -m d:g:$GROUP:rwX
