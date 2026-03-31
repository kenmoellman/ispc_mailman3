#!/bin/bash
# Regenerate postfix_vmap from postfix_lmtp after Mailman3 alias regeneration.
# postfix_vmap maps list addresses to themselves in virtual_alias_maps,
# making Postfix accept them as valid recipients on virtual_mailbox_domains.
LMTP=/var/lib/mailman3/data/postfix_lmtp
VMAP=/var/lib/mailman3/data/postfix_vmap

awk '/^[^#]/ && NF>=2 {print $1, $1}' "$LMTP" > "$VMAP"
postmap hash:"$VMAP"
chown list:list "$VMAP" "$VMAP.db"
