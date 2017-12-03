#!/bin/bash

# 自动测试
# tests/test.sh -d -v 5.0,5.4

# 默认的版本
versions=(5.0 5.1 5.2 5.3 5.4)
# 是否重新安装, 默认 否定
reinstall=''
# 是否直接运行测试 (不更新 composer, 配置相关文件), 默认为 否定
direct_test=''

# 获取参数
while getopts ":v:rd" opt; do
    case $opt in
        v)
            # versions=$OPTARG
            IFS_OLD=$IFS
            # 设置 IFS 为 逗号或分号
            IFS=':,'
            # 将输入的选项值读入到 versions 变量中
            # versions 返回的是一个数组 (而不是字符串)
            read -r -a versions <<< "$OPTARG"
            IFS=$IFS_OLD
            ;;
        r)
            reinstall='true'
            ;;
        d)
            direct_test='true'
            ;;
        \?)
            # 这里用来匹配未定义的参数, 比如 --
            echo "Invalid option: -$OPTARG"
            ;;
    esac
done

# 需要向 config/app.php 中注册的服务类
services=(
    'Barryvdh\\Debugbar\\ServiceProvider'
    'Frostrain\\Laravel\\ConsoleDebug\\ConsoleDebugServiceProvider'
)
# 测试命令的类名
cmd_name='App\\Console\\Commands\\TestCommand'
# root=`pwd`
test_root=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd $test_root/..
root=$(pwd)

# 声明字典
declare -A env
env=(
    [DB_DATABASE]=laravel_pkg_test
    [DB_USERNAME]=root2
    [DB_PASSWORD]=pass
)

errors=()
# 设置测试失败的版本
function add_error_version (){
    ver=$1
    errors=(${errors[@]} $1)
}

# 设置数据库
function setup_database(){
    database=$1
    # Travis-CI 环境
    if [ "$TRAVIS" ]; then
        env[DB_USERNAME]=$DB_USERNAME
        env[DB_PASSWORD]=$DB_PASSWORD

        env[DB_DATABASE]=$database
        # 数据库中有 点号时(5.0) 需要使用 ` 括起来
        mysql -e "create database IF NOT EXISTS \`$database\`;" -uroot
        mysql -e "grant all privileges on *.* to '$DB_USERNAME'@'localhost' with grant option;" -uroot
    fi
}

# 向文件中添加额外内容
function add_line (){
    find=$1
    add=$2
    file=$3
    exist=$(grep $add $file)
    # 只有 $add 不存在于 $file 中时, 才添加, 避免重复添加
    if [ ! "$exist" ]; then
        sed -i "/$find/a $add" $file
    fi
}

# 对指定的laravel版本进行测试
function test_version() {
    version=$1
    cd $root
    mkdir -p laravel
    echo 'testing with laravel '$version
    app_dir=$root/laravel/laravel_$version

    setup_database laravel_$version

    # need_setup 表示是否需要初始配置
    need_setup=''
    if [ "$reinstall" -o ! -d "$app_dir" ]; then
        need_setup='true'
        echo 'install laravel '$version
        rm -rf $app_dir
        composer create-project -q laravel/laravel $app_dir $version'.*'
        cd $app_dir
        composer require wikimedia/composer-merge-plugin
    fi

    cd $app_dir

    if [ "$need_setup" -o ! "$direct_test" ]; then
        echo 'setup laravel '$version
        # 配置 .env
        for key in $(echo ${!env[*]})
        do
            sed -ri "s/("$key"=).*/\1"${env[$key]}"/" .env
        done

        php artisan migrate

        # 配置 laravel 中的 composer.json
        # 使用 composer-merge-plugin 来合并插件的依赖
        composer_file=composer.json
        # 检查 composer 文件中是否已经有了 'ignore-duplicates' 字符
        exist=$(grep 'ignore-duplicates' $composer_file)
        if [ ! "$exist" ]; then
            echo 'insert merge-plugin to comopser.json';
            # 在 composer 文件中(extra行的后面)插入 composer_insert.txt 文件中的字符
            extra_exist=$(grep 'extra' $composer_file)
            if [ ! "$extra_exist" ]; then
                # laravel 里面的 composer.json 文件中 没有extra字段 (5.0-5.4版本)
                sed -i "/license/r $test_root/composer_insert_after_license.txt" $composer_file
            else
                # laravel 里面的 composer.json 文件中 有extra字段 (5.5+版本)
                sed -i "/extra/r $test_root/composer_insert_in_extra.txt" $composer_file
            fi
        fi
        composer update

        # 在 config/app.php 中添加服务
        current='App\\Providers\\RouteServiceProvider'
        for s in ${services[@]}
        do
            # a命令 会自动插入一个新行
            # sed -i "/$current/a '$s'," config/app.php
            add_line $current "'$s'," config/app.php
            current=$s
        done

        mkdir -p $app_dir/app/Console/Commands
        cp -f $test_root/TestCommand.php $app_dir/app/Console/Commands/
        # sed -i "/protected \$commands/a '$cmd_name'," app/Console/Kernel.php
        add_line 'protected \$commands' "'$cmd_name'," $app_dir/app/Console/Kernel.php
    fi

    cd $app_dir

    # 如果 test 命令执行出错, 退出非0 (表示失败)
    # 貌似 test 命令就算抛出异常也不是 stderr..而是 stdout
    php artisan test -v|tee debug.txt || { add_error_version $version; return; }

    # 如果 debug 输出中 没有对应关键字, 退出非0
    grep 'info' debug.txt > /dev/null || { add_error_version $version; return; }
    grep "select \* from `users`" debug.txt > /dev/null || { add_error_version $version; return; }

    echo "laravel $version test successed"
}

# test_version 5.0
for ver in "${versions[@]}"
do
    test_version $ver
done

echo ''
echo ''

if [ ${#errors[@]} -gt 0 ]; then
    echo "-----------------------------------"
    echo "  failed versions: "${errors[@]} >&2
    echo "-----------------------------------"
    exit 1
else
    echo "-----------------------------------"
    echo "  passed versions: "${versions[@]}
    echo "-----------------------------------"
fi
