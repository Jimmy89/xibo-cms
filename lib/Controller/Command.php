<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Command.php)
 */


namespace Xibo\Controller;


use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\CommandFactory;
use Xibo\Helper\Sanitize;

class Command extends Base
{
    public function displayPage()
    {
        $this->getState()->template = 'command-page';
    }

    /**
     * @SWG\Get(
     *  path="/command",
     *  operationId="commandSearch",
     *  tags={"command"},
     *  summary="Command Search",
     *  description="Search this users Commands",
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="formData",
     *      description="Filter by Command Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="command",
     *      in="formData",
     *      description="Filter by Command Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="Filter by Command Code",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Command")
     *      )
     *  )
     * )
     */
    function grid()
    {
        $filter = [
            'commandId' => Sanitize::getInt('commandId'),
            'command' => Sanitize::getString('command'),
            'code' => Sanitize::getString('code')
        ];

        $commands = CommandFactory::query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($commands as $command) {
            /* @var \Xibo\Entity\Command $command */

            if ($this->isApi())
                return;

            $command->includeProperty('buttons');

            // Default Layout
            $command->buttons[] = array(
                'id' => 'command_button_edit',
                'url' => $this->urlFor('command.edit.form', ['id' => $command->commandId]),
                'text' => __('Edit')
            );

            if ($this->getUser()->checkDeleteable($command)) {
                $command->buttons[] = array(
                    'id' => 'command_button_delete',
                    'url' => $this->urlFor('command.delete.form', ['id' => $command->commandId]),
                    'text' => __('Delete')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = CommandFactory::countLast();
        $this->getState()->setData($commands);
    }

    /**
     * Add Command Form
     */
    public function addForm()
    {
        $this->getState()->template = 'command-form-add';
    }

    /**
     * Edit Command
     * @param int $commandId
     */
    public function editForm($commandId)
    {
        $command = CommandFactory::getById($commandId);

        if ($command->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'command-form-edit';
        $this->getState()->setData([
            'command' => $command
        ]);
    }

    /**
     * Delete Command
     * @param int $commandId
     */
    public function deleteForm($commandId)
    {
        $command = CommandFactory::getById($commandId);

        if ($command->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'command-form-delete';
        $this->getState()->setData([
            'command' => $command
        ]);
    }

    /**
     * Add Command
     *
     * @SWG\Post(
     *  path="/command",
     *  operationId="commandAdd",
     *  tags={"command"},
     *  summary="Command Add",
     *  description="Add a Command",
     *  @SWG\Parameter(
     *      name="command",
     *      in="formData",
     *      description="The Command Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description for the command",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A unique code for this command",
     *      type="strgin",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Command"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add()
    {
        $command = new \Xibo\Entity\Command();
        $command->command = Sanitize::getString('command');
        $command->description = Sanitize::getString('description');
        $command->code = Sanitize::getString('code');
        $command->userId = $this->getUser()->userId;
        $command->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $command->command),
            'id' => $command->commandId,
            'data' => $command
        ]);
    }

    /**
     * Edit Command
     * @param int $commandId
     *
     * @SWG\Put(
     *  path="/command/{commandId}",
     *  operationId="commandEdit",
     *  tags={"command"},
     *  summary="Edit Command",
     *  description="Edit the provided command",
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="path",
     *      description="The Command Id to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="command",
     *      in="formData",
     *      description="The Command Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description for the command",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A unique code for this command",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Command")
     *  )
     * )
     */
    public function edit($commandId)
    {
        $command = CommandFactory::getById($commandId);

        if ($command->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $command->command = Sanitize::getString('command');
        $command->description = Sanitize::getString('description');
        $command->code = Sanitize::getString('code');
        $command->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $command->command),
            'id' => $command->commandId,
            'data' => $command
        ]);
    }

    /**
     * Delete Command
     * @param int $commandId
     *
     * @SWG\Delete(
     *  path="/command/{commandId}",
     *  operationId="commandDelete",
     *  tags={"command"},
     *  summary="Delete Command",
     *  description="Delete the provided command",
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="path",
     *      description="The Command Id to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete($commandId)
    {
        $command = CommandFactory::getById($commandId);

        if ($command->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $command->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $command->command)
        ]);
    }
}