module.exports.loadConfiguration = async (aws, prefix) => {
    const prefix_length = prefix.length + 1
    const reducer = (accumulator, item) => {
        accumulator[item.Name.substring(prefix_length)] = item.Value
        return accumulator
    }
    const ssm = new aws.SSM({region: 'eu-central-1'})
    const parameters = {
        Path: prefix,
        Recursive: true,
        WithDecryption: true
    }
    let parameterArray = await ssm.getParametersByPath(parameters)
        .promise()
        .then((data) => {return data.Parameters})
        .catch((error) => {console.log(error); return []})

    return parameterArray.reduce(reducer, {})
}
