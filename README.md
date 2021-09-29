# contao-entity-approval-bundle

## Workflow

![workflow](docs/img/approval-process.jpg)

### config.yml

```yaml
huh_entity_approval:
  tl_submission:
    initial_auditor_groups: '1'
    auditor_groups: '1,2,6'
    initial_auditor_mode: 'random'
    publish_field: 'published'
    invert_publish_field: true
    emails:
      auditor_changed_former: true
      auditor_changed_new: true
      state_changed_author: true
```

`vendor/bin/contao-console workflow:dump entity_approval | dot -Tpng -o vendor/heimrichhannot/contao-entity-approval-bundle/docs/img/approval-process.jpg`