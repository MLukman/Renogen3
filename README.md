![Renogen](public\ui\logo-circle.svg)

# Renogen

Renogen is a release management approval & tracking platform. It allows developers to request approval from the system environment owners for the releases of developed enhancements as well as for system environment administrators to execute the steps needed to release the enhancements.

## Concepts

### Project

A project represents a group of developers, quality assurance team, system owners, system admins etc working to release enhancements onto a specific system environment.

### Deployment

A deployment represents a scheduled date and time to release the enhancements. Commonly refers to "deployment window".

### Deployment Item

A deployment item represents an enhancement that to be released onto the system environment.

### Activity

An activity represent a step that needs to be execute to release an enhancement.

### Activity Template

An activity template is a template created at the project level that allows creation of activities using a configuration form.

### Checklist

A checklist is a list of tasks created at a deployment level to allow the project users to coordinate related tasks related to the deployment apart from the execution of the activities.

## Roles

| Role in Renogen projects | Capabilities                                                 | Possible role(s) in development team                       |
| ------------------------ | ------------------------------------------------------------ | ---------------------------------------------------------- |
| approval                 | - Manage project settings & membership<br />- Create deployments<br />- Approve deployment items | Project Manager, Product Owner, Release Manager            |
| entry                    | - Request deployments (via Deployment Request)<br />Create deployment items<br />- Create deployment activities<br />- Submit deployment items | Development Team, Configuration Team                       |
| review                   | - Review submitted deployment items                          | QA Team, Technical Leader                                  |
| execute                  | - View run books<br />- Update run book items                | System Administrator, Server Administrator, Operation Team |

## Common flow

1. "Entry" user requests deployment window
2. "Approval" user creates deployment window
3. "Entry" user registers deployment items & specifies deployment activities
4. "Review" user reviews deployment items
5. "Approval" user approves deployment items
6. "Execute" user executes deployment activities
7. A deployment item changes status to "Completed" once all its activities are set to "Completed"

